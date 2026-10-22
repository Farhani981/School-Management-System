<?php
namespace App\Http\Controllers;

use App\Models\{FeeVoucher, FeePayment, Student, SchoolClass, AcademicSession};
use App\Services\FeeBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Pagination\LengthAwarePaginator;

class FeeBillingController extends Controller
{
    private function vouchers(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:150', 'class_id' => 'nullable|integer|exists:school_classes,id',
            'status' => 'nullable|in:All,Unpaid,Partial,Paid,Overdue,Cancelled', 'month' => 'nullable|date_format:Y-m', 'page' => 'nullable|integer|min:1']);
        return FeeVoucher::with(['payments'])->when($request->filled('class_id'), fn ($q) => $q->where('school_class_id', $request->class_id))
            ->when($request->filled('month'), function ($q) use ($request) { [$year, $month] = explode('-', $request->month); $q->where('year', $year)->where('month', $month); })
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$request->search.'%')
                ->orWhere('student_name', 'like', '%'.$request->search.'%')->orWhere('admission_no', 'like', '%'.$request->search.'%')))->orderByDesc('id')->get();
    }
    private function paginate($rows, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->get('page', 1));
        return new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page, ['path' => $request->url(), 'query' => $request->query()]);
    }
    public function index(Request $request)
    {
        $rows = $this->vouchers($request);
        if ($request->filled('status') && $request->status !== 'All') $rows = $rows->filter(fn ($v) => $v->display_status === $request->status);
        return view('fees.billing.index', [
            'vouchers' => $this->paginate($rows, $request),
            'classes' => \Illuminate\Support\Facades\Cache::remember('fee_billing_classes', 300, fn () => SchoolClass::orderBy('name')->get()),
            'sessions' => \Illuminate\Support\Facades\Cache::remember('fee_billing_sessions', 300, fn () => AcademicSession::orderByDesc('is_current')->get()),
            'module' => 'Vouchers'
        ]);
    }
    public function generate(Request $request, FeeBillingService $service)
    {
        $data = $request->validate(['month' => 'required|integer|between:1,12', 'year' => 'required|integer|between:1900,9999',
            'academic_session_id' => 'required|integer|exists:academic_sessions,id', 'school_class_id' => 'nullable|integer|exists:school_classes,id',
            'student_id' => 'nullable|integer|exists:students,id,deleted_at,NULL']);
        $students = Student::where('status', 'Active')->where('academic_session_id', $data['academic_session_id'])
            ->when(!empty($data['school_class_id']), fn ($q) => $q->where('class_id', $data['school_class_id']))
            ->when(!empty($data['student_id']), fn ($q) => $q->whereKey($data['student_id']))->orderBy('id')->limit(501)->get();
        if ($students->isEmpty() || $students->count() > 500) throw \Illuminate\Validation\ValidationException::withMessages(['student_id' => 'Select a class/session with 1–500 active students, or choose a single student.']);
        $created = DB::transaction(function () use ($students, $data, $request, $service) {
            $created = 0;
            foreach ($students as $student) { $voucher = $service->generate($student, (int) $data['month'], (int) $data['year'], $request->user()); if ($voucher->wasRecentlyCreated) $created++; }
            return $created;
        }, 3);
        return redirect()->route('fees.vouchers.index')->with('success', $created.' vouchers generated. '.($students->count() - $created).' existing vouchers kept.');
    }
    public function show(FeeVoucher $voucher, FeeBillingService $service)
    {
        $voucher->load(['items', 'payments.receiver', 'creator']);
        return view('fees.billing.show', ['voucher' => $voucher, 'fine' => $service->fine($voucher), 'balance' => $service->balance($voucher),
            'paid' => $voucher->payments->whereNull('reversed_at')->sum('amount_cents'), 'methods' => FeeBillingService::METHODS,
            'otherBalance' => FeeVoucher::with('payments')->where('student_id', $voucher->student_id)->whereKeyNot($voucher->id)->whereNull('cancelled_at')->get()->sum(fn ($v) => $service->balance($v))]);
    }
    public function collection(Request $request)
    {
        $rows = $this->vouchers($request)->filter(fn ($v) => !$v->cancelled_at && $v->balance_cents > 0);
        $payments = FeePayment::with(['voucher', 'receiver'])->orderByDesc('id')->limit(20)->get();
        return view('fees.billing.index', ['vouchers' => $this->paginate($rows, $request), 'classes' => SchoolClass::orderBy('name')->get(), 'payments' => $payments, 'module' => 'Collection']);
    }
    public function collect(Request $request, FeeVoucher $voucher, FeeBillingService $service)
    {
        $data = $request->validate(['amount' => 'required|numeric|decimal:0,2|min:0.01|max:99999999.99', 'payment_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'method' => ['required', Rule::in(FeeBillingService::METHODS)], 'reference' => 'nullable|string|max:150', 'notes' => 'nullable|string|max:1000', 'submission_token' => 'required|uuid']);
        $payment = $service->collect($voucher, $data, $request->user());
        return redirect()->route('fees.receipts.show', $payment)->with('success', 'Payment recorded. Receipt '.$payment->number.'.');
    }
    public function receipt(FeePayment $payment)
    {
        return view('fees.billing.receipt', ['payment' => $payment->load(['voucher', 'receiver', 'reverser'])]);
    }
    public function reverse(Request $request, FeePayment $payment, FeeBillingService $service)
    {
        $reason = $request->validate(['reason' => 'required|string|min:3|max:1000'])['reason'];
        $service->reverse($payment, $reason, $request->user());
        return redirect()->route('fees.receipts.show', $payment)->with('success', 'Payment reversed. Voucher balance restored.');
    }
    public function cancel(Request $request, FeeVoucher $voucher, FeeBillingService $service)
    {
        $reason = $request->validate(['reason' => 'required|string|min:3|max:1000'])['reason'];
        $service->cancel($voucher, $reason, $request->user());
        return redirect()->route('fees.vouchers.show', $voucher)->with('success', 'Voucher cancelled. Its history remains available.');
    }
    public function defaulters(Request $request)
    {
        $rows = $this->vouchers($request)->filter(fn ($v) => !$v->cancelled_at && $v->due_date->lt(today()) && $v->balance_cents > 0);
        if ($request->get('export') === 'csv') return $this->csv('defaulters.csv', ['Voucher', 'Student', 'Admission No', 'Class', 'Due Date', 'Outstanding'], $rows->map(fn ($v) => [$v->number, $v->student_name, $v->admission_no, $v->class_name, $v->due_date->toDateString(), number_format($v->balance_cents / 100, 2, '.', '')]));
        return view('fees.billing.index', ['vouchers' => $this->paginate($rows, $request), 'outstanding' => $rows->sum(fn ($v) => $v->balance_cents),
            'classes' => SchoolClass::orderBy('name')->get(), 'module' => 'Defaulters']);
    }
    public function reports(Request $request)
    {
        $data = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'class_id' => 'nullable|integer|exists:school_classes,id', 'export' => 'nullable|in:csv,students', 'student_search' => 'nullable|string|max:150', 'fee_status' => 'nullable|in:Paid,Partial,Unpaid', 'student_page' => 'nullable|integer|min:1']);
        $from = $data['from'] ?? today()->startOfMonth()->toDateString(); $to = $data['to'] ?? today()->toDateString();
        if ($to < $from) throw \Illuminate\Validation\ValidationException::withMessages(['to' => 'End date must be on or after the start date.']);
        $payments = FeePayment::with(['voucher', 'receiver'])->whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)
            ->when(!empty($data['class_id']), fn ($q) => $q->whereHas('voucher', fn ($v) => $v->where('school_class_id', $data['class_id'])))->orderByDesc('payment_date')->get();
        $active = $payments->whereNull('reversed_at');
        $vouchers = FeeVoucher::with('payments')->whereNull('cancelled_at')->when(!empty($data['class_id']), fn ($q) => $q->where('school_class_id', $data['class_id']))->get();
        $billing = app(FeeBillingService::class);
        $studentFees = $vouchers->groupBy('student_id')->map(function ($rows) use ($billing) {
            $latest = $rows->sortByDesc('id')->first();
            $paid = $rows->sum(fn ($voucher) => $voucher->payments->whereNull('reversed_at')->sum('amount_cents'));
            $remaining = $rows->sum(fn ($voucher) => $voucher->balance_cents);
            return ['student_id'=>$latest->student_id, 'name'=>$latest->student_name, 'admission_no'=>$latest->admission_no,
                'classes'=>$rows->pluck('class_name')->unique()->join(', '), 'vouchers'=>$rows->count(),
                'total'=>$rows->sum(fn ($voucher) => $voucher->total_cents + $billing->fine($voucher)),
                'paid'=>$paid, 'remaining'=>$remaining, 'status'=>$remaining === 0 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Unpaid'),
                'overdue'=>$rows->sum(fn ($voucher) => $voucher->due_date->lt(today()) ? $voucher->balance_cents : 0)];
        })->sortByDesc('remaining')->values();
        $studentFeeCounts = $studentFees->countBy('status');
        $search = mb_strtolower(trim($data['student_search'] ?? ''));
        if ($search !== '') $studentFees = $studentFees->filter(fn ($row) => str_contains(mb_strtolower($row['name'].' '.$row['admission_no']), $search));
        if (!empty($data['fee_status'])) $studentFees = $studentFees->where('status', $data['fee_status']);
        if ($request->get('export') === 'students') return $this->csv('student-fee-balances.csv', ['Student','Admission No','Class','Vouchers','Total Fee PKR','Paid PKR','Remaining PKR','Overdue PKR','Status'], $studentFees->map(fn ($row) => [$row['name'],$row['admission_no'],$row['classes'],$row['vouchers'],number_format($row['total']/100,2,'.',''),number_format($row['paid']/100,2,'.',''),number_format($row['remaining']/100,2,'.',''),number_format($row['overdue']/100,2,'.',''),$row['status']]));
        $studentFees = new LengthAwarePaginator($studentFees->forPage((int) ($data['student_page'] ?? 1),20)->values(), $studentFees->count(),20,(int) ($data['student_page'] ?? 1), ['path'=>$request->url(),'query'=>$request->except('export'),'pageName'=>'student_page']);
        if ($request->get('export') === 'csv') return $this->csv('fee-collection.csv', ['Receipt', 'Date', 'Student', 'Voucher', 'Method', 'Amount', 'Status'], $payments->map(fn ($p) => [$p->number, $p->payment_date->toDateString(), $p->voucher->student_name, $p->voucher->number, $p->method, number_format($p->amount_cents / 100, 2, '.', ''), $p->reversed_at ? 'Reversed' : 'Received']));
        $classTotals = $vouchers->groupBy('school_class_id')->map(fn ($rows, $id) => ['class' => $rows->first()->class_name,
            'collected' => $active->filter(fn ($p) => $p->voucher->school_class_id == $id)->sum('amount_cents'), 'outstanding' => $rows->sum(fn ($v) => $v->balance_cents)]);
        return view('fees.billing.reports', ['from' => $from, 'to' => $to, 'payments' => $this->paginate($payments, $request), 'studentFees'=>$studentFees, 'studentFeeCounts'=>$studentFeeCounts,
            'collected' => $active->sum('amount_cents'), 'outstanding' => $vouchers->sum(fn ($v) => $v->balance_cents),
            'todayCollected' => $active->filter(fn ($p) => $p->payment_date->isToday())->sum('amount_cents'), 'classTotals' => $classTotals,
            'classes' => SchoolClass::orderBy('name')->get(), 'methodTotals' => $active->groupBy('method')->map(fn ($rows) => $rows->sum('amount_cents'))]);
    }
    public function studentHistory(Student $student)
    {
        $vouchers = FeeVoucher::with(['payments.voucher', 'student', 'items'])->where('student_id',$student->id)->whereNull('cancelled_at')->orderByDesc('year')->orderByDesc('month')->get();
        $sessions = AcademicSession::whereIn('id',$vouchers->pluck('academic_session_id'))->pluck('name','id');
        $billing = app(FeeBillingService::class);
        $months = $vouchers->groupBy(fn($voucher) => $voucher->academic_session_id.'-'.$voucher->year.'-'.$voucher->month)->map(function($rows) use($billing,$sessions) {
            $first = $rows->first();
            $paid = $rows->sum(fn($voucher) => $voucher->payments->whereNull('reversed_at')->sum('amount_cents'));
            $remaining = $rows->sum(fn($voucher) => $voucher->balance_cents);
            $payments = $rows->flatMap->payments->whereNull('reversed_at')->sortByDesc('payment_date');
            return ['label'=>\Carbon\Carbon::create($first->year,$first->month,1)->format('F Y'), 'session'=>$sessions->get($first->academic_session_id),
                'total'=>$rows->sum(fn($voucher) => $voucher->total_cents + $billing->fine($voucher)), 'paid'=>$paid, 'remaining'=>$remaining,
                'status'=>$remaining === 0 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Unpaid'), 'last_payment'=>$payments->first()?->payment_date,
                'overdue'=>$rows->sum(fn($voucher) => $voucher->due_date->lt(today()) ? $voucher->balance_cents : 0), 'vouchers'=>$rows];
        })->values();
        $monthCounts = $months->countBy('status');
        $payments = $vouchers->flatMap->payments->sortByDesc('payment_date');
        return view('fees.billing.monthly-history',compact('student','months','monthCounts','payments'));
    }
    private function csv(string $name, array $headers, $rows)
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $file = fopen('php://output', 'w'); fputcsv($file, $headers);
            foreach ($rows as $row) fputcsv($file, array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value, $row));
            fclose($file);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

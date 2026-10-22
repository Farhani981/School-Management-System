<?php

namespace App\Http\Controllers;

use App\Models\{AccountExpense, AccountIncome, Payroll};
use App\Services\TeacherPayroll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\Rule;

class AccountOverviewController extends Controller
{
    public function dashboard(Request $request)
    {
        return view('accounts.dashboard', ['totals' => $this->totals($this->ledger())]);
    }

    private function ledger()
    {
        $income = DB::table('account_incomes')->selectRaw("income_date as date, 'Income' as type, title as description, category, amount_cents as signed_cents, payment_method, received_from as party");
        $expenses = DB::table('expenses_tables')->selectRaw("expense_date as date, 'Expense' as type, COALESCE(title, description) as description, category, -amount_cents as signed_cents, payment_method, paid_to as party");
        $fees = DB::table('fee_payments')->whereNull('reversed_at')->selectRaw("payment_date as date, 'Income' as type, number as description, 'Student Fees' as category, amount_cents as signed_cents, method as payment_method, reference as party");
        $salary = DB::table('payrolls')->join('teachers', 'teachers.id', '=', 'payrolls.teacher_id')->where('payrolls.status', 'Paid')->whereNotNull('paid_at')->selectRaw("DATE(paid_at) as date, 'Salary' as type, teachers.name as description, 'Staff Salary' as category, -CAST(ROUND(net_salary * 100) AS SIGNED) as signed_cents, payment_method, teachers.name as party");

        return DB::query()->fromSub($income->unionAll($expenses)->unionAll($fees)->unionAll($salary), 'ledger');
    }

    private function totals($query): array
    {
        $sums = (clone $query)->selectRaw("COALESCE(SUM(CASE WHEN type = 'Income' THEN signed_cents ELSE 0 END), 0) as income, COALESCE(SUM(CASE WHEN type = 'Expense' THEN -signed_cents ELSE 0 END), 0) as expenses, COALESCE(SUM(CASE WHEN type = 'Salary' THEN -signed_cents ELSE 0 END), 0) as salaries, COALESCE(SUM(signed_cents), 0) as balance")->first();
        return (array) $sums;
    }

    public function transactions(Request $request)
    {
        $data = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'type' => ['nullable', Rule::in(['Income', 'Expense', 'Salary'])], 'export' => 'nullable|in:csv']);
        $query = $this->ledger()->when($data['from'] ?? null, fn ($q, $date) => $q->where('date', '>=', $date))->when($data['to'] ?? null, fn ($q, $date) => $q->where('date', '<=', $date))->when($data['type'] ?? null, fn ($q, $type) => $q->where('type', $type));
        $totals = $this->totals($query);
        $query->orderByDesc('date')->orderBy('type')->orderBy('description')->orderBy('signed_cents');
        if (($data['export'] ?? null) === 'csv') {
            return response()->streamDownload(function () use ($query) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Date', 'Type', 'Description', 'Category', 'Amount (PKR)', 'Payment Method', 'Party']);
                foreach ($query->cursor() as $row) {
                    $values = [$row->date, $row->type, $row->description, $row->category, number_format($row->signed_cents / 100, 2, '.', ''), $row->payment_method, $row->party];
                    foreach ([2, 3, 5, 6] as $index) {
                        if (preg_match('/^[=+\-@\t\r]/', (string) $values[$index])) $values[$index] = "'".$values[$index];
                    }
                    fputcsv($file, $values);
                }
                fclose($file);
            }, 'accounts-report.csv', ['Content-Type' => 'text/csv']);
        }
        return view('accounts.transactions', ['rows' => $query->paginate(25)->withQueryString(), 'totals' => $totals]);
    }

    public function income()
    {
        return view('accounts.income', ['incomes' => AccountIncome::orderByDesc('income_date')->orderByDesc('id')->paginate(20)]);
    }

    public function storeIncome(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255', 'category' => 'required|string|max:100',
            'amount' => 'required|numeric|decimal:0,2|min:0.01|max:99999999.99',
            'income_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'payment_method' => ['required', Rule::in(['Cash', 'Bank Transfer', 'Cheque'])],
            'received_from' => 'required|string|max:255', 'description' => 'nullable|string|max:5000',
            'reference' => 'nullable|string|max:150', 'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
        [$whole, $fraction] = array_pad(explode('.', (string) $data['amount'], 2), 2, '');
        unset($data['amount'], $data['attachment']);
        $data['amount_cents'] = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        $data['created_by'] = $request->user()->id;
        $data['attachment_path'] = $request->file('attachment')?->store('accounts/income', 'local');
        AccountIncome::create($data);
        return back()->with('success', 'Income recorded successfully.');
    }

    public function attachment(string $type, int $id)
    {
        abort_unless(in_array($type, ['income', 'expense']), 404);
        $record = $type === 'income' ? AccountIncome::findOrFail($id) : AccountExpense::findOrFail($id);
        abort_unless($record->attachment_path && Storage::disk('local')->exists($record->attachment_path), 404);
        return Storage::disk('local')->download($record->attachment_path);
    }

    public function salaries(Request $request)
    {
        $request->validate(['month' => 'nullable|date_format:Y-m']);
        $month = $request->input('month', now()->format('Y-m'));
        return view('accounts.salaries', ['month' => $month, 'payrolls' => Payroll::with('teacher.designation')->whereDate('month', $month.'-01')->paginate(25)->withQueryString()]);
    }

    public function generate(Request $request, TeacherPayroll $service)
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m']);
        $count = $service->generate($data['month']);
        return redirect()->route('accounts.salaries.index', $data)->with('success', "Generated {$count} salary records.");
    }

    public function pay(Request $request, Payroll $payroll)
    {
        $data = $request->validate(['payment_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'payment_method' => ['required', Rule::in(['Cash', 'Bank Transfer', 'Cheque'])]]);
        DB::transaction(function () use ($payroll, $data) {
            $locked = Payroll::whereKey($payroll->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'Paid') $locked->update(['status' => 'Paid', 'paid_at' => $data['payment_date'], 'payment_method' => $data['payment_method']]);
        });
        return back()->with('success', 'Salary marked paid.');
    }
}

<?php

namespace App\Services;

use App\Models\FeePayment;
use App\Models\FeeSetting;
use App\Models\FeeVoucher;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Collection;

class StudentFeeService
{
    public function __construct(
        protected FeeBillingService $billingService
    ) {}

    /**
     * Get fee summary for the resolved student.
     *
     * @return array{
     *     totalPending: float,
     *     totalPaidThisSession: float,
     *     overdueAmount: float,
     *     nextDueDate: ?string,
     *     status: string,
     *     latest: ?array,
     *     latestUnpaidChallan: ?array
     * }
     */
    public function getSummary(Student $student): array
    {
        $vouchers = FeeVoucher::with(['payments', 'items'])
            ->where('student_id', $student->id)
            ->whereNull('cancelled_at')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->get();

        $totalPendingCents = 0;
        $overdueCents = 0;
        $unpaidVouchers = [];
        $today = today();

        foreach ($vouchers as $voucher) {
            $balance = $this->billingService->balance($voucher);
            if ($balance > 0) {
                $totalPendingCents += $balance;
                $unpaidVouchers[] = $voucher;

                if ($voucher->due_date && $voucher->due_date->lt($today)) {
                    $overdueCents += $balance;
                }
            }
        }

        // Determine next upcoming due date (prefer upcoming due dates >= today, else most recent due date)
        $nextDueDate = null;
        $upcoming = collect($unpaidVouchers)
            ->filter(fn ($v) => $v->due_date && $v->due_date->gte($today))
            ->sortBy(fn ($v) => $v->due_date->timestamp)
            ->first();

        if ($upcoming) {
            $nextDueDate = $upcoming->due_date->format('Y-m-d');
        } elseif (! empty($unpaidVouchers)) {
            $past = collect($unpaidVouchers)
                ->filter(fn ($v) => $v->due_date)
                ->sortByDesc(fn ($v) => $v->due_date->timestamp)
                ->first();
            $nextDueDate = $past?->due_date?->format('Y-m-d');
        }

        // Total paid in current academic session
        $sessionVoucherIds = $student->academic_session_id
            ? $vouchers->where('academic_session_id', $student->academic_session_id)->pluck('id')
            : $vouchers->pluck('id');

        $totalPaidThisSessionCents = FeePayment::whereIn('fee_voucher_id', $sessionVoucherIds)
            ->whereNull('reversed_at')
            ->sum('amount_cents');

        // Status rule: overdue if any overdue balance exists, else pending if total pending > 0, else paid
        $status = 'paid';
        if ($overdueCents > 0) {
            $status = 'overdue';
        } elseif ($totalPendingCents > 0) {
            $status = 'pending';
        }

        // Latest unpaid challan
        $latestUnpaid = null;
        $latestVoucher = collect($unpaidVouchers)->first();
        if ($latestVoucher) {
            $monthName = $latestVoucher->month
                ? Carbon::createFromDate($latestVoucher->year, $latestVoucher->month, 1)->format('F Y')
                : (string) $latestVoucher->year;

            $latestUnpaid = [
                'id' => $latestVoucher->id,
                'challan_no' => $latestVoucher->number,
                'challanNo' => $latestVoucher->number,
                'month' => $monthName,
                'amount' => round($this->billingService->balance($latestVoucher) / 100, 2),
                'due_date' => $latestVoucher->due_date?->format('Y-m-d'),
                'dueDate' => $latestVoucher->due_date?->format('Y-m-d'),
                'status' => $latestVoucher->due_date && $latestVoucher->due_date->lt($today) ? 'overdue' : 'unpaid',
            ];
        }

        return [
            'totalPending' => round($totalPendingCents / 100, 2),
            'totalPaidThisSession' => round($totalPaidThisSessionCents / 100, 2),
            'overdueAmount' => round($overdueCents / 100, 2),
            'nextDueDate' => $nextDueDate,
            'status' => $status,
            'latest' => $latestUnpaid,
            'latestUnpaidChallan' => $latestUnpaid,
        ];
    }

    /**
     * Get list of challans for the resolved student with optional status/session filter.
     *
     * @param Student $student
     * @param string $status 'all'|'paid'|'unpaid'|'overdue'
     * @param string|int|null $session
     * @return Collection
     */
    public function getChallans(Student $student, string $status = 'all', $session = null): Collection
    {
        $query = FeeVoucher::with(['items', 'payments'])
            ->where('student_id', $student->id)
            ->whereNull('cancelled_at')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id');

        if (! empty($session)) {
            $query->where('academic_session_id', $session);
        }

        $vouchers = $query->get();
        $today = today();

        // Calculate balances and map
        $mapped = $vouchers->map(function (FeeVoucher $voucher) use ($vouchers, $today) {
            $fineCents = $this->billingService->fine($voucher);
            $balanceCents = $this->billingService->balance($voucher);
            $paidCents = $voucher->payments->whereNull('reversed_at')->sum('amount_cents');

            // Arrears: sum of unpaid balances of vouchers issued prior to this one for this student
            $arrearsCents = $vouchers->filter(function (FeeVoucher $prev) use ($voucher) {
                if ($prev->id === $voucher->id) {
                    return false;
                }
                // Check if prev is older chronologically
                return ($prev->year < $voucher->year)
                    || ($prev->year === $voucher->year && $prev->month < $voucher->month)
                    || ($prev->year === $voucher->year && $prev->month === $voucher->month && $prev->id < $voucher->id);
            })->sum(fn ($prev) => $this->billingService->balance($prev));

            $monthLabel = $voucher->month
                ? Carbon::createFromDate($voucher->year, $voucher->month, 1)->format('F Y')
                : (string) $voucher->year;

            // Status string
            if ($balanceCents === 0) {
                $challanStatus = 'paid';
            } elseif ($voucher->due_date && $voucher->due_date->lt($today)) {
                $challanStatus = 'overdue';
            } elseif ($paidCents > 0) {
                $challanStatus = 'partial';
            } else {
                $challanStatus = 'unpaid';
            }

            $paidDate = $voucher->settled_on?->format('Y-m-d');
            if (! $paidDate && $paidCents > 0) {
                $latestPayment = $voucher->payments->whereNull('reversed_at')->sortByDesc('payment_date')->first();
                $paidDate = $latestPayment?->payment_date?->format('Y-m-d');
            }

            $feeHeads = $voucher->items->map(fn ($item) => [
                'id' => $item->fee_type_id ?? $item->id,
                'name' => $item->name,
                'frequency' => $item->frequency,
                'amount' => round($item->amount_cents / 100, 2),
                'discount' => round($item->discount_cents / 100, 2),
                'net' => round($item->net_cents / 100, 2),
                'override_reason' => $item->override_reason,
            ])->values()->all();

            return [
                'id' => $voucher->id,
                'challan_no' => $voucher->number,
                'challanNo' => $voucher->number,
                'month' => $monthLabel,
                'period' => sprintf('%04d-%02d', $voucher->year, $voucher->month),
                'academic_session_id' => $voucher->academic_session_id,
                'fee_heads' => $feeHeads,
                'feeHeads' => $feeHeads,
                'discount' => round($voucher->discount_cents / 100, 2),
                'late_fine' => round($fineCents / 100, 2),
                'lateFine' => round($fineCents / 100, 2),
                'arrears' => round($arrearsCents / 100, 2),
                'total' => round($voucher->total_cents / 100, 2),
                'paid_amount' => round($paidCents / 100, 2),
                'paidAmount' => round($paidCents / 100, 2),
                'balance' => round($balanceCents / 100, 2),
                'due_date' => $voucher->due_date?->format('Y-m-d'),
                'dueDate' => $voucher->due_date?->format('Y-m-d'),
                'status' => $challanStatus,
                'paid_date' => $paidDate,
                'paidDate' => $paidDate,
            ];
        });

        // Filter by requested status
        $statusNormalized = strtolower(trim($status));
        if ($statusNormalized === 'paid') {
            return $mapped->filter(fn ($item) => $item['status'] === 'paid')->values();
        } elseif ($statusNormalized === 'unpaid') {
            return $mapped->filter(fn ($item) => in_array($item['status'], ['unpaid', 'partial', 'overdue']))->values();
        } elseif ($statusNormalized === 'overdue') {
            return $mapped->filter(fn ($item) => $item['status'] === 'overdue')->values();
        }

        return $mapped->values();
    }

    /**
     * Get single challan details strictly verifying ownership.
     * Returns null if not owned by student.
     */
    public function getChallan(Student $student, int $id): ?FeeVoucher
    {
        return FeeVoucher::where('student_id', $student->id)
            ->whereNull('cancelled_at')
            ->with([
                'items',
                'payments' => fn ($q) => $q->whereNull('reversed_at')->with('receiver'),
                'student.schoolClass',
                'student.section',
            ])
            ->find($id);
    }

    /**
     * Get detailed formatted array for single challan.
     */
    public function getChallanDetails(Student $student, int $id): ?array
    {
        $voucher = $this->getChallan($student, $id);
        if (! $voucher) {
            return null;
        }

        $allVouchers = FeeVoucher::where('student_id', $student->id)
            ->whereNull('cancelled_at')
            ->with('payments')
            ->get();

        $fineCents = $this->billingService->fine($voucher);
        $balanceCents = $this->billingService->balance($voucher);
        $paidCents = $voucher->payments->whereNull('reversed_at')->sum('amount_cents');

        $arrearsCents = $allVouchers->filter(function (FeeVoucher $prev) use ($voucher) {
            if ($prev->id === $voucher->id) {
                return false;
            }
            return ($prev->year < $voucher->year)
                || ($prev->year === $voucher->year && $prev->month < $voucher->month)
                || ($prev->year === $voucher->year && $prev->month === $voucher->month && $prev->id < $voucher->id);
        })->sum(fn ($prev) => $this->billingService->balance($prev));

        $monthLabel = $voucher->month
            ? Carbon::createFromDate($voucher->year, $voucher->month, 1)->format('F Y')
            : (string) $voucher->year;

        $today = today();
        if ($balanceCents === 0) {
            $challanStatus = 'paid';
        } elseif ($voucher->due_date && $voucher->due_date->lt($today)) {
            $challanStatus = 'overdue';
        } elseif ($paidCents > 0) {
            $challanStatus = 'partial';
        } else {
            $challanStatus = 'unpaid';
        }

        $paidDate = $voucher->settled_on?->format('Y-m-d');
        if (! $paidDate && $paidCents > 0) {
            $latestPayment = $voucher->payments->whereNull('reversed_at')->sortByDesc('payment_date')->first();
            $paidDate = $latestPayment?->payment_date?->format('Y-m-d');
        }

        $feeHeads = $voucher->items->map(fn ($item) => [
            'id' => $item->fee_type_id ?? $item->id,
            'name' => $item->name,
            'frequency' => $item->frequency,
            'amount' => round($item->amount_cents / 100, 2),
            'discount' => round($item->discount_cents / 100, 2),
            'net' => round($item->net_cents / 100, 2),
            'override_reason' => $item->override_reason,
        ])->values()->all();

        $payments = $voucher->payments->whereNull('reversed_at')->map(fn ($p) => [
            'id' => $p->id,
            'receipt_no' => $p->number,
            'receiptNo' => $p->number,
            'amount' => round($p->amount_cents / 100, 2),
            'payment_date' => $p->payment_date?->format('Y-m-d'),
            'paymentDate' => $p->payment_date?->format('Y-m-d'),
            'method' => $p->method,
            'reference' => $p->reference,
            'received_by' => $p->receiver?->name ?? 'Accounts Office',
            'receivedBy' => $p->receiver?->name ?? 'Accounts Office',
        ])->values()->all();

        return [
            'id' => $voucher->id,
            'challan_no' => $voucher->number,
            'challanNo' => $voucher->number,
            'student_name' => $voucher->student_name,
            'studentName' => $voucher->student_name,
            'admission_no' => $voucher->admission_no,
            'admissionNo' => $voucher->admission_no,
            'class_name' => $voucher->class_name,
            'className' => $voucher->class_name,
            'month' => $monthLabel,
            'period' => sprintf('%04d-%02d', $voucher->year, $voucher->month),
            'subtotal' => round($voucher->subtotal_cents / 100, 2),
            'discount' => round($voucher->discount_cents / 100, 2),
            'late_fine' => round($fineCents / 100, 2),
            'lateFine' => round($fineCents / 100, 2),
            'arrears' => round($arrearsCents / 100, 2),
            'total' => round($voucher->total_cents / 100, 2),
            'paid_amount' => round($paidCents / 100, 2),
            'paidAmount' => round($paidCents / 100, 2),
            'balance' => round($balanceCents / 100, 2),
            'due_date' => $voucher->due_date?->format('Y-m-d'),
            'dueDate' => $voucher->due_date?->format('Y-m-d'),
            'grace_days' => $voucher->grace_days,
            'graceDays' => $voucher->grace_days,
            'status' => $challanStatus,
            'paid_date' => $paidDate,
            'paidDate' => $paidDate,
            'fee_heads' => $feeHeads,
            'feeHeads' => $feeHeads,
            'payments' => $payments,
        ];
    }

    /**
     * Get payment history list for the resolved student.
     */
    public function getPayments(Student $student): Collection
    {
        return FeePayment::whereHas('voucher', fn ($q) => $q->where('student_id', $student->id)->whereNull('cancelled_at'))
            ->whereNull('reversed_at')
            ->with(['voucher', 'receiver'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FeePayment $p) => [
                'id' => $p->id,
                'receipt_no' => $p->number,
                'receiptNo' => $p->number,
                'challan_no' => $p->voucher?->number,
                'challanNo' => $p->voucher?->number,
                'challan_id' => $p->fee_voucher_id,
                'challanId' => $p->fee_voucher_id,
                'amount' => round($p->amount_cents / 100, 2),
                'payment_date' => $p->payment_date?->format('Y-m-d'),
                'paymentDate' => $p->payment_date?->format('Y-m-d'),
                'method' => $p->method,
                'received_by' => $p->receiver?->name ?? 'Accounts Office',
                'receivedBy' => $p->receiver?->name ?? 'Accounts Office',
                'reference' => $p->reference,
                'notes' => $p->notes,
                'balance_after' => round($p->balance_after_cents / 100, 2),
                'balanceAfter' => round($p->balance_after_cents / 100, 2),
                'status' => 'received',
            ]);
    }

    /**
     * Get single payment strictly verifying student ownership.
     */
    public function getPayment(Student $student, int $id): ?FeePayment
    {
        return FeePayment::whereHas('voucher', fn ($q) => $q->where('student_id', $student->id)->whereNull('cancelled_at'))
            ->whereNull('reversed_at')
            ->with([
                'voucher.student.schoolClass',
                'voucher.student.section',
                'receiver',
            ])
            ->find($id);
    }

    /**
     * Stream a clean, printable PDF Challan.
     */
    public function streamChallanPdf(Student $student, int $voucherId)
    {
        $voucher = $this->getChallan($student, $voucherId);
        if (! $voucher) {
            abort(404, 'Fee challan not found.');
        }

        $allVouchers = FeeVoucher::where('student_id', $student->id)
            ->whereNull('cancelled_at')
            ->with('payments')
            ->get();

        $fineCents = $this->billingService->fine($voucher);
        $balanceCents = $this->billingService->balance($voucher);
        $paidCents = $voucher->payments->whereNull('reversed_at')->sum('amount_cents');

        $arrearsCents = $allVouchers->filter(function (FeeVoucher $prev) use ($voucher) {
            if ($prev->id === $voucher->id) {
                return false;
            }
            return ($prev->year < $voucher->year)
                || ($prev->year === $voucher->year && $prev->month < $voucher->month)
                || ($prev->year === $voucher->year && $prev->month === $voucher->month && $prev->id < $voucher->id);
        })->sum(fn ($prev) => $this->billingService->balance($prev));

        $settings = FeeSetting::find(1);

        // QR Code with Challan verification payload
        $qrPayload = 'CHALLAN:'.$voucher->number.'|STUDENT:'.$voucher->admission_no.'|DUE:'.$voucher->due_date?->format('Y-m-d').'|TOTAL:PKR'.number_format($voucher->total_cents / 100, 2);
        $qr = (new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'scale' => 4,
            'imageBase64' => true,
        ])))->render($qrPayload);

        $monthLabel = $voucher->month
            ? Carbon::createFromDate($voucher->year, $voucher->month, 1)->format('F Y')
            : (string) $voucher->year;

        $data = [
            'voucher' => $voucher,
            'student' => $student,
            'monthLabel' => $monthLabel,
            'fine' => $fineCents,
            'balance' => $balanceCents,
            'paid' => $paidCents,
            'arrears' => $arrearsCents,
            'settings' => $settings,
            'qr' => $qr,
            'schoolName' => config('app.school_name', 'Crescent Model High School'),
            'schoolAddress' => 'Main Academic Campus, Model Town, Lahore',
            'schoolPhone' => '+92 (42) 3591-2800',
            'bankName' => 'Allied Bank Limited (ABL)',
            'bankBranch' => 'Model Town Branch (0142)',
            'accountTitle' => 'Crescent Model School Collection A/C',
            'accountNo' => '0142-0098765401',
            'iban' => 'PK36ABPA0000014200987654',
        ];

        return Pdf::loadView('portal.pdf.challan', $data)
            ->setPaper('a4', 'landscape')
            ->stream('challan-'.$voucher->number.'.pdf');
    }

    /**
     * Stream a clean, printable PDF Payment Receipt.
     */
    public function streamPaymentReceiptPdf(Student $student, int $paymentId)
    {
        $payment = $this->getPayment($student, $paymentId);
        if (! $payment) {
            abort(404, 'Payment receipt not found.');
        }

        $voucher = $payment->voucher;

        $qrPayload = 'RECEIPT:'.$payment->number.'|VOUCHER:'.$voucher->number.'|DATE:'.$payment->payment_date?->format('Y-m-d').'|PAID:PKR'.number_format($payment->amount_cents / 100, 2);
        $qr = (new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'scale' => 4,
            'imageBase64' => true,
        ])))->render($qrPayload);

        $data = [
            'payment' => $payment,
            'voucher' => $voucher,
            'student' => $student,
            'qr' => $qr,
            'schoolName' => config('app.school_name', 'Crescent Model High School'),
            'schoolAddress' => 'Main Academic Campus, Model Town, Lahore',
            'schoolPhone' => '+92 (42) 3591-2800',
        ];

        return Pdf::loadView('portal.pdf.receipt', $data)
            ->setPaper('a4', 'portrait')
            ->stream('receipt-'.$payment->number.'.pdf');
    }
}

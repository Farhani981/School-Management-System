<?php
namespace App\Services;

use App\Models\{FeeVoucher, FeePayment, FeeSetting, Student, User, Activity};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeBillingService
{
    public const METHODS = ['Cash', 'Bank Transfer', 'Card', 'Mobile Wallet', 'Cheque'];
    public function generate(Student $student, int $month, int $year, User $actor): FeeVoucher
    {
        return DB::transaction(function () use ($student, $month, $year, $actor) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            if ($student->status !== 'Active' || !$student->class_id || !$student->academic_session_id) $this->fail('student_id', 'Choose an active student with a class and academic session.');
            $key = $student->id.':'.$year.':'.$month;
            if ($existing = FeeVoucher::where('active_billing_key', $key)->first()) return $existing;
            $calculation = app(FeeCalculatorService::class)->calculateForStudent($student, $month, $year);
            if (!$calculation['items']) $this->fail('student_id', 'No billable fee structure exists for '.$student->admission_no.'. Set up class fees first.');
            $settings = FeeSetting::find(1);
            $voucher = FeeVoucher::create(['active_billing_key' => $key, 'student_id' => $student->id,
                'academic_session_id' => $student->academic_session_id, 'school_class_id' => $student->class_id,
                'year' => $year, 'month' => $month, 'due_date' => $calculation['billing_date'],
                'student_name' => trim($student->first_name.' '.$student->last_name), 'admission_no' => $student->admission_no,
                'class_name' => $student->schoolClass->name, 'subtotal_cents' => FeeCalculatorService::cents($calculation['subtotal']),
                'discount_cents' => FeeCalculatorService::cents($calculation['discount']), 'total_cents' => FeeCalculatorService::cents($calculation['total']),
                'fine_type' => $settings?->fine_type ?? 'none', 'fine_amount_cents' => FeeCalculatorService::cents($settings?->fine_amount ?? 0),
                'fine_cap_cents' => $settings?->fine_max_cap === null ? null : FeeCalculatorService::cents($settings->fine_max_cap),
                'grace_days' => $settings?->grace_days ?? 0, 'settled_on' => $calculation['total'] == 0 ? today()->toDateString() : null, 'created_by' => $actor->id]);
            $voucher->update(['number' => ($settings?->voucher_prefix ?: 'FV').'-'.str_pad((string) $voucher->id, 7, '0', STR_PAD_LEFT)]);
            foreach ($calculation['items'] as $item) $voucher->items()->create(['fee_type_id' => $item['fee_type_id'], 'name' => $item['fee_type'],
                'frequency' => $item['frequency'], 'amount_cents' => FeeCalculatorService::cents($item['amount']),
                'discount_cents' => FeeCalculatorService::cents($item['discount']), 'net_cents' => FeeCalculatorService::cents($item['net_amount']), 'override_reason' => $item['override_reason']]);
            $this->audit($actor, 'Fee voucher generated: '.$voucher->number, 'FeeVoucher:'.$voucher->id);
            return $voucher;
        }, 3);
    }
    public function fine(FeeVoucher $voucher, ?string $asOf = null): int
    {
        if ($voucher->cancelled_at || $voucher->total_cents === 0) return 0;
        $date = $voucher->settled_on ? CarbonImmutable::parse($voucher->settled_on) : CarbonImmutable::parse($asOf ?? today()->toDateString());
        $deadline = CarbonImmutable::parse($voucher->due_date)->addDays($voucher->grace_days);
        if ($date->lte($deadline) || $voucher->fine_type === 'none') return 0;
        $days = (int) $deadline->diffInDays($date);
        $fine = $voucher->fine_type === 'per_day' ? $days * $voucher->fine_amount_cents : $voucher->fine_amount_cents;
        return $voucher->fine_cap_cents > 0 ? min($fine, $voucher->fine_cap_cents) : $fine;
    }
    public function balance(FeeVoucher $voucher, ?string $asOf = null): int
    {
        if ($voucher->cancelled_at) return 0;
        return max(0, $voucher->total_cents + $this->fine($voucher, $asOf) - $voucher->payments->whereNull('reversed_at')->sum('amount_cents'));
    }
    public function collect(FeeVoucher $voucher, array $data, User $actor): FeePayment
    {
        return DB::transaction(function () use ($voucher, $data, $actor) {
            $voucher = FeeVoucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            $amount = FeeCalculatorService::cents($data['amount']);
            if ($existing = FeePayment::where('submission_token', $data['submission_token'])->first()) {
                if ($existing->fee_voucher_id !== $voucher->id || $existing->amount_cents !== $amount || $existing->payment_date->toDateString() !== $data['payment_date'] || $existing->method !== $data['method'] || ($existing->reference ?? '') !== ($data['reference'] ?? '')) $this->fail('amount', 'This submission was already used for a different payment. Refresh the form.');
                return $existing;
            }
            if ($voucher->cancelled_at) $this->fail('amount', 'A cancelled voucher cannot receive payments.');
            $date = CarbonImmutable::parse($data['payment_date']);
            $latest = $voucher->payments->whereNull('reversed_at')->max('payment_date');
            if ($date->gt(today()) || $date->lt(CarbonImmutable::create($voucher->year, $voucher->month, 1)) || ($latest && $date->lt($latest))) $this->fail('payment_date', 'Use a date in the billing period or later, no earlier than the last payment, and not in the future.');
            $balance = $this->balance($voucher, $data['payment_date']);
            if ($amount <= 0 || $amount > $balance) $this->fail('amount', 'Payment must be greater than zero and cannot exceed the outstanding balance.');
            $fine = $this->fine($voucher, $data['payment_date']);
            $payment = $voucher->payments()->create(['submission_token' => $data['submission_token'], 'amount_cents' => $amount,
                'payment_date' => $data['payment_date'], 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null,
                'fine_cents' => $fine, 'balance_after_cents' => $balance - $amount, 'received_by' => $actor->id]);
            $prefix = FeeSetting::find(1)?->receipt_prefix ?: 'FR';
            $payment->update(['number' => $prefix.'-'.str_pad((string) $payment->id, 7, '0', STR_PAD_LEFT)]);
            if ($balance === $amount) $voucher->update(['settled_on' => $data['payment_date']]);
            $this->audit($actor, 'Fee payment received: '.$payment->number, 'FeePayment:'.$payment->id);
            return $payment;
        }, 3);
    }
    public function reverse(FeePayment $payment, string $reason, User $actor): void
    {
        DB::transaction(function () use ($payment, $reason, $actor) {
            $voucher = FeeVoucher::whereKey($payment->fee_voucher_id)->lockForUpdate()->firstOrFail();
            $payment = FeePayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->reversed_at) $this->fail('reason', 'This payment has already been reversed.');
            $payment->update(['reversed_at' => now(), 'reversal_reason' => $reason, 'reversed_by' => $actor->id]);
            $voucher->update(['settled_on' => null]);
            $this->audit($actor, 'Fee payment reversed: '.$payment->number.' / '.$reason, 'FeePayment:'.$payment->id);
        }, 3);
    }
    public function cancel(FeeVoucher $voucher, string $reason, User $actor): void
    {
        DB::transaction(function () use ($voucher, $reason, $actor) {
            $voucher = FeeVoucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            if ($voucher->cancelled_at || $voucher->payments()->whereNull('reversed_at')->exists()) $this->fail('reason', 'Reverse all payments before cancelling an active voucher.');
            $voucher->update(['active_billing_key' => null, 'cancelled_at' => now(), 'cancellation_reason' => $reason, 'cancelled_by' => $actor->id]);
            $this->audit($actor, 'Fee voucher cancelled: '.$voucher->number.' / '.$reason, 'FeeVoucher:'.$voucher->id);
        }, 3);
    }
    private function fail(string $field, string $message): never { throw ValidationException::withMessages([$field => $message]); }
    private function audit(User $actor, string $description, string $subject): void
    {
        Activity::create(['description' => $description, 'type' => 'fees', 'subject' => $subject, 'user_id' => $actor->id, 'created_at' => now()]);
        DB::afterCommit(fn () => DashboardStatsService::invalidate());
    }
}

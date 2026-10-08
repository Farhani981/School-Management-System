<?php

namespace App\Services;

use App\Models\FeeSetting;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\Student;
use Carbon\CarbonImmutable;

class FeeCalculatorService
{
    public function calculateForStudent(Student $student, int $month, int $year): array
    {
        if ($month < 1 || $month > 12 || $year < 1900 || $year > 9999) {
            throw new \InvalidArgumentException('Provide a valid month and year.');
        }
        $period = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $settings = FeeSetting::find(1);
        $billingDate = $period->day(min($settings?->due_day ?? 10, $period->daysInMonth))->toDateString();
        $structures = $student->class_id && $student->academic_session_id
            ? FeeStructure::with('feeType')->where('school_class_id', $student->class_id)->where('academic_session_id', $student->academic_session_id)->whereHas('feeType', fn ($q) => $q->active())->orderBy('fee_type_id')->get() : collect();
        $overrides = $student->feeOverrides()->get()->keyBy('fee_type_id');
        $items = [];
        $remaining = [];
        $subtotal = 0;
        foreach ($structures as $structure) {
            $type = $structure->feeType;
            if ($type->frequency === 'quarterly' && $month % 3 !== 0) {
                continue;
            }
            if (in_array($type->frequency, ['yearly', 'one_time']) && $this->alreadyBilled($student, $type, $year)) {
                continue;
            }
            $override = $overrides->get($type->id);
            $amount = max(0, self::cents($override ? $override->amount : $structure->amount));
            $subtotal += $amount;
            $remaining[$type->id] = $amount;
            $items[$type->id] = ['fee_type_id' => $type->id, 'fee_type' => $type->name, 'frequency' => $type->frequency, 'base_amount' => self::money(self::cents($structure->amount)), 'amount' => self::money($amount), 'override_reason' => $override?->reason];
        }
        // Stable assignment order: each discount applies to the remaining eligible amount.
        $assignments = $student->feeDiscounts()->with('discount')->whereHas('discount', fn ($q) => $q->where('is_active', true))->whereDate('valid_from', '<=', $billingDate)->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $billingDate))->orderBy('id')->get();
        foreach ($assignments as $assignment) {
            $this->applyDiscount($remaining, $assignment->discount->type, $assignment->discount->value, $assignment->fee_type_id === null ? null : (int) $assignment->fee_type_id);
        }
        if ($settings?->sibling_discount_enabled && $this->isAdditionalSibling($student, $billingDate)) {
            $this->applyDiscount($remaining, $settings->sibling_discount_type, $settings->sibling_discount_value);
        }
        foreach ($items as $id => &$item) {
            $item['discount'] = self::money(self::cents($item['amount']) - $remaining[$id]);
            $item['net_amount'] = self::money($remaining[$id]);
        }
        unset($item);
        $total = array_sum($remaining);

        return ['items' => array_values($items), 'subtotal' => self::money($subtotal), 'discount' => self::money($subtotal - $total), 'fine' => 0.0, 'arrears' => 0.0, 'total' => self::money(max(0, $total)), 'billing_date' => $billingDate, 'provisional' => true];
    }

    protected function alreadyBilled(Student $student, FeeType $type, int $year): bool
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('fee_voucher_items')) return false;
        return \App\Models\FeeVoucherItem::where('fee_type_id', $type->id)->whereHas('voucher', fn ($query) => $query
            ->where('student_id', $student->id)->whereNull('cancelled_at')
            ->when($type->frequency === 'yearly', fn ($q) => $q->where('year', $year)))->exists();
    }

    private function isAdditionalSibling(Student $student, string $billingDate): bool
    {
        return $student->siblings()->where('status', 'Active')->where('academic_session_id', $student->academic_session_id)->whereDate('admission_date', '<=', $billingDate)->where(function ($q) use ($student) {
            $date = $student->admission_date->toDateString();
            $q->whereDate('admission_date', '<', $date)->orWhere(fn ($q) => $q->whereDate('admission_date', $date)->where('id', '<', $student->id));
        })->exists();
    }

    private function applyDiscount(array &$remaining, string $type, $value, ?int $feeType = null): void
    {
        $eligible = array_filter($remaining, fn ($amount, $id) => $amount > 0 && ($feeType === null || $feeType === $id), ARRAY_FILTER_USE_BOTH);
        if (! $eligible) {
            return;
        }
        if ($type === 'percent') {
            $points = min(10000, max(0, self::cents($value)));
            foreach ($eligible as $id => $amount) {
                $remaining[$id] -= min($amount, intdiv($amount * $points + 5000, 10000));
            }

            return;
        }
        $sum = array_sum($eligible);
        $deduction = min($sum, max(0, self::cents($value)));
        $shares = [];
        $fractions = [];
        $allocated = 0;
        foreach ($eligible as $id => $amount) {
            $share = ($amount / $sum) * $deduction;
            $shares[$id] = min($amount, (int) floor($share));
            $fractions[$id] = $share - $shares[$id];
            $allocated += $shares[$id];
        }
        arsort($fractions, SORT_NUMERIC);
        foreach ($fractions as $id => $fraction) {
            if ($allocated >= $deduction) {
                break;
            }
            if ($shares[$id] < $eligible[$id]) {
                $shares[$id]++;
                $allocated++;
            }
        }
        foreach ($shares as $id => $share) {
            $remaining[$id] -= $share;
        }
    }

    public static function cents($value): int
    {
        return (int) round((float) $value * 100);
    }

    private static function money(int $cents): float
    {
        return $cents / 100;
    }
}

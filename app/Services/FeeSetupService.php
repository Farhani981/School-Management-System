<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Discount;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentDiscount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FeeSetupService
{
    public function saveStructure(array $data): void
    {
        DB::transaction(function () use ($data) {
            SchoolClass::whereKey($data['school_class_id'])->lockForUpdate()->firstOrFail();
            $types = FeeType::active()->orderBy('id')->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $submitted = array_map('intval', array_keys($data['amounts']));
            sort($submitted);
            if ($submitted !== $types || array_filter($types, fn ($id) => ! array_key_exists($id, $data['amounts']))) {
                throw ValidationException::withMessages(['amounts' => 'Fee types changed or the selection is incomplete. Reload the structure before saving.']);
            }
            $key = ['academic_session_id' => $data['academic_session_id'], 'school_class_id' => $data['school_class_id']];
            foreach ($types as $id) {
                FeeStructure::updateOrCreate($key + ['fee_type_id' => $id], ['amount' => $data['amounts'][$id] ?? 0]);
            }
            self::log('Fee structure saved for class #'.$data['school_class_id'].' / session #'.$data['academic_session_id'].'.', 'fee_structure', 'school_classes:'.$data['school_class_id']);
        }, 3);
    }

    public function copyStructure(array $data): void
    {
        if ((int) $data['school_class_id'] === (int) $data['from_school_class_id'] && (int) $data['academic_session_id'] === (int) $data['from_academic_session_id']) {
            throw ValidationException::withMessages(['from_school_class_id' => 'Choose a different source class or session.']);
        }
        DB::transaction(function () use ($data) {
            SchoolClass::whereIn('id', [$data['school_class_id'], $data['from_school_class_id']])->orderBy('id')->lockForUpdate()->get();
            $ids = FeeType::active()->orderBy('id')->lockForUpdate()->pluck('id');
            $source = FeeStructure::where('academic_session_id', $data['from_academic_session_id'])->where('school_class_id', $data['from_school_class_id'])->whereIn('fee_type_id', $ids)->lockForUpdate()->get();
            if ($source->isEmpty()) {
                throw ValidationException::withMessages(['from_school_class_id' => 'The source has no active fee structure to copy.']);
            }
            $target = ['academic_session_id' => $data['academic_session_id'], 'school_class_id' => $data['school_class_id']];
            FeeStructure::where($target)->whereIn('fee_type_id', $ids)->delete();
            foreach ($source as $row) {
                FeeStructure::create($target + ['fee_type_id' => $row->fee_type_id, 'amount' => $row->amount]);
            }
            self::log('Fee structure copied from class #'.$data['from_school_class_id'].' / session #'.$data['from_academic_session_id'].' to class #'.$data['school_class_id'].' / session #'.$data['academic_session_id'].'.', 'fee_structure', 'school_classes:'.$data['school_class_id']);
        }, 3);
    }

    public function assignDiscount(array $data): void
    {
        DB::transaction(function () use ($data) {
            Student::whereKey($data['student_id'])->lockForUpdate()->firstOrFail();
            $discount = Discount::whereKey($data['discount_id'])->where('is_active', true)->lockForUpdate()->first();
            if (! $discount) {
                throw ValidationException::withMessages(['discount_id' => 'Select an active discount.']);
            }
            $fee = $data['fee_type_id'] ?? null;
            if ($fee && ! FeeType::active()->whereKey($fee)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['fee_type_id' => 'Select an active fee type.']);
            }
            $overlap = StudentDiscount::where('student_id', $data['student_id'])->where('discount_id', $data['discount_id'])->whereDate('valid_from', '<=', $data['valid_to'] ?? '9999-12-31')->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $data['valid_from']));
            if ($fee) {
                $overlap->where(fn ($q) => $q->whereNull('fee_type_id')->orWhere('fee_type_id', $fee));
            }
            if ($overlap->exists()) {
                throw ValidationException::withMessages(['discount_id' => 'This discount already covers the student and fee during overlapping dates.']);
            }
            StudentDiscount::create($data);
            self::log('Discount "'.$discount->name.'" assigned to student #'.$data['student_id'].'.', 'discount', 'students:'.$data['student_id']);
        }, 3);
    }

    public function typeInUse(FeeType $type): bool
    {
        if ($type->structures()->exists() || $type->studentDiscounts()->exists() || $type->overrides()->exists()) {
            return true;
        }
        foreach (['fee_voucher_items', 'voucher_items', 'fee_vouchers'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'fee_type_id') && DB::table($table)->where('fee_type_id', $type->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    public static function log(string $description, string $type, string $subject): void
    {
        if (Schema::hasTable('activities')) {
            Activity::create(['description' => $description, 'type' => $type, 'subject' => $subject, 'user_id' => auth()->id(), 'created_at' => now()]);
        }
        DashboardStatsService::invalidate();
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => DashboardStatsService::invalidate());
        }
    }
}

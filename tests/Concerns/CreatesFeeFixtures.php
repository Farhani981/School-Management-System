<?php

namespace Tests\Concerns;

use App\Models\AcademicSession;
use App\Models\Discount;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;

trait CreatesFeeFixtures
{
    protected function feeStudent(array $data = []): Student
    {
        $session = AcademicSession::firstOrCreate(['name' => '2026-27'], ['is_current' => true]);
        $class = SchoolClass::firstOrCreate(['name' => 'Class 1']);
        $number = Student::withTrashed()->count() + 1;

        return Student::create(array_replace(['admission_no' => 'ADM-2026-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT), 'first_name' => 'Ali', 'last_name' => 'Khan', 'father_name' => 'Ahmed Khan', 'date_of_birth' => '2016-01-01', 'gender' => 'Male', 'phone' => '03001234567', 'address' => 'Lahore', 'admission_date' => '2026-08-01', 'roll_number' => (string) $number, 'status' => 'Active', 'class_id' => $class->id, 'academic_session_id' => $session->id], $data));
    }

    protected function feeType(Student $student, string $name = 'Tuition', string $frequency = 'monthly', $amount = 1000): FeeType
    {
        $type = FeeType::create(['name' => $name, 'frequency' => $frequency, 'is_active' => true]);
        FeeStructure::create(['academic_session_id' => $student->academic_session_id, 'school_class_id' => $student->class_id, 'fee_type_id' => $type->id, 'amount' => $amount]);

        return $type;
    }

    protected function feeDiscount(Student $student, string $type, $value, ?int $feeType = null, array $dates = []): Discount
    {
        $discount = Discount::create(['name' => 'Discount '.Discount::count(), 'type' => $type, 'value' => $value, 'is_active' => true]);
        $student->feeDiscounts()->create(array_replace(['discount_id' => $discount->id, 'fee_type_id' => $feeType, 'valid_from' => '2026-01-01', 'valid_to' => null], $dates));

        return $discount;
    }
}

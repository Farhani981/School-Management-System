<?php

namespace Tests\Unit;

use App\Models\FeeSetting;
use App\Models\FeeType;
use App\Models\Student;
use App\Services\FeeCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFeeFixtures;
use Tests\TestCase;

class FeeCalculatorServiceTest extends TestCase
{
    use CreatesFeeFixtures, RefreshDatabase;

    public function test_override_replaces_structure_and_zero_is_a_valid_waiver(): void
    {
        $student = $this->feeStudent();
        $type = $this->feeType($student);
        $override = $student->feeOverrides()->create(['fee_type_id' => $type->id, 'amount' => 750, 'reason' => 'Special concession']);
        $calculator = new FeeCalculatorService;
        $result = $calculator->calculateForStudent($student, 10, 2026);
        $this->assertEquals(750, $result['subtotal']);
        $this->assertEquals(750, $result['items'][0]['amount']);
        $override->update(['amount' => 0]);
        $this->assertEquals(0, $calculator->calculateForStudent($student, 10, 2026)['total']);
    }

    public function test_fixed_discount_applies_once_to_all_fees(): void
    {
        $student = $this->feeStudent();
        $this->feeType($student);
        $this->feeType($student, 'Computer', 'monthly', 200);
        $this->feeDiscount($student, 'fixed', 150);
        $result = (new FeeCalculatorService)->calculateForStudent($student, 10, 2026);
        $this->assertEquals(1200, $result['subtotal']);
        $this->assertEquals(150, $result['discount']);
        $this->assertEquals(1050, $result['total']);
        $this->assertEquals(1050, array_sum(array_column($result['items'], 'net_amount')));
    }

    public function test_percent_discount_only_applies_to_selected_fee_type(): void
    {
        $student = $this->feeStudent();
        $tuition = $this->feeType($student);
        $this->feeType($student, 'Computer', 'monthly', 200);
        $this->feeDiscount($student, 'percent', 10, $tuition->id);
        $result = (new FeeCalculatorService)->calculateForStudent($student, 10, 2026);
        $this->assertEquals(100, $result['discount']);
        $this->assertEquals(1100, $result['total']);
    }

    public function test_discounts_never_make_total_or_item_amounts_negative(): void
    {
        $student = $this->feeStudent();
        $this->feeType($student);
        $this->feeDiscount($student, 'fixed', 9000);
        $this->feeDiscount($student, 'percent', 100);
        $result = (new FeeCalculatorService)->calculateForStudent($student, 10, 2026);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals(1000, $result['discount']);
        foreach ($result['items'] as $item) {
            $this->assertGreaterThanOrEqual(0, $item['net_amount']);
        }
        $this->assertEquals(0, $result['fine']);
        $this->assertEquals(0, $result['arrears']);
    }

    public function test_frequency_rules_and_phase_two_billing_hook(): void
    {
        $student = $this->feeStudent();
        $this->feeType($student, 'Monthly', 'monthly', 100);
        $this->feeType($student, 'Exam', 'quarterly', 30);
        $this->feeType($student, 'Annual', 'yearly', 50);
        $this->feeType($student, 'Admission', 'one_time', 25);
        $calculator = new FeeCalculatorService;
        $this->assertEquals(175, $calculator->calculateForStudent($student, 10, 2026)['total']);
        $this->assertEquals(205, $calculator->calculateForStudent($student, 12, 2026)['total']);
        $billed = new class extends FeeCalculatorService
        {
            protected function alreadyBilled(Student $student, FeeType $type, int $year): bool
            {
                return true;
            }
        };
        $this->assertEquals(100, $billed->calculateForStudent($student, 10, 2026)['total']);
    }

    public function test_dates_inactive_discounts_and_cent_rounding(): void
    {
        $student = $this->feeStudent();
        $this->feeType($student, 'Tuition', 'monthly', 0.05);
        $this->feeDiscount($student, 'fixed', 100, null, ['valid_from' => '2026-10-11']);
        $inactive = $this->feeDiscount($student, 'percent', 100);
        $inactive->update(['is_active' => false]);
        $this->feeDiscount($student, 'percent', 50);
        $result = (new FeeCalculatorService)->calculateForStudent($student, 10, 2026);
        $this->assertEquals(0.03, $result['discount']);
        $this->assertEquals(0.02, $result['total']);
        $this->assertSame('2026-10-10', $result['billing_date']);
    }

    public function test_sibling_rule_starts_with_second_active_child(): void
    {
        $first = $this->feeStudent(['family_id' => 'FAM-1']);
        $this->feeType($first);
        $second = $this->feeStudent(['family_id' => 'FAM-1', 'first_name' => 'Sara']);
        FeeSetting::findOrFail(1)->update(['sibling_discount_enabled' => true, 'sibling_discount_type' => 'percent', 'sibling_discount_value' => 10]);
        $calculator = new FeeCalculatorService;
        $this->assertEquals(1000, $calculator->calculateForStudent($first, 10, 2026)['total']);
        $this->assertEquals(900, $calculator->calculateForStudent($second, 10, 2026)['total']);
        FeeSetting::findOrFail(1)->update(['sibling_discount_type' => 'fixed', 'sibling_discount_value' => 50]);
        $this->assertEquals(950, $calculator->calculateForStudent($second, 10, 2026)['total']);
        $first->update(['status' => 'Inactive']);
        $this->assertEquals(1000, $calculator->calculateForStudent($second, 10, 2026)['total']);
    }

    public function test_missing_assignment_and_inactive_fee_types_return_empty_items(): void
    {
        $student = $this->feeStudent();
        $type = $this->feeType($student);
        $type->update(['is_active' => false]);
        $calculator = new FeeCalculatorService;
        $this->assertSame([],$calculator->calculateForStudent($student,10,2026)['items']);
        $student->update(['class_id' => null]);
        $this->assertEquals(0,$calculator->calculateForStudent($student,10,2026)['total']);
    }
}

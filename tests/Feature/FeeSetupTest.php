<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Discount;
use App\Models\FeeSetting;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\StudentDiscount;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\FeeSetupSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesFeeFixtures;
use Tests\TestCase;

class FeeSetupTest extends TestCase
{
    use CreatesFeeFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-02 10:00:00'));
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
    }

    public function test_auth_sidebar_five_children_placeholders_and_all_setup_pages(): void
    {
        $this->get(route('fees.setup.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'Teacher']))->get(route('fees.setup.types.index'))->assertForbidden();
        $this->admin();
        $student = $this->feeStudent();
        $this->feeType($student);
        foreach (['fees.setup.types.index', 'fees.setup.types.create', 'fees.setup.discounts.index', 'fees.setup.discounts.create', 'fees.setup.settings.index'] as $name) {
            $this->get(route($name))->assertOk();
        }
        $page = $this->get(route('fees.setup.structures.index', ['academic_session_id' => $student->academic_session_id, 'school_class_id' => $student->class_id]))->assertOk()->assertSee('Structure total');
        preg_match('/id="fees-submenu".*?<\/div><\/div>/s', $page->getContent(), $menu);
        $this->assertSame(5, substr_count($menu[0], 'nav-child'));
        foreach (['vouchers', 'collection', 'defaulters', 'reports'] as $module) {
            $this->get(route('fees.'.$module.'.index'))->assertOk()->assertDontSee('Coming soon')->assertSee('active-parent');
        }
        $this->get(route('fees.preview', [$student, 'month' => 10, 'year' => 2026]))->assertOk()->assertSee('1,000.00');
    }

    public function test_fee_type_crud_search_and_delete_protection(): void
    {
        $this->admin();
        $data = ['name' => 'Tuition', 'frequency' => 'monthly', 'is_active' => 1];
        $this->post(route('fees.setup.types.store'), $data)->assertSessionHasNoErrors();
        $type = FeeType::firstOrFail();
        $this->post(route('fees.setup.types.store'), $data)->assertSessionHasErrors('name');
        $this->get(route('fees.setup.types.index', ['search' => 'Tuition']))->assertOk()->assertSee('Tuition');
        $this->get(route('fees.setup.types.edit', $type))->assertOk();
        $this->put(route('fees.setup.types.update', $type), array_replace($data, ['name' => 'Tuition Updated']))->assertSessionHasNoErrors();
        $this->delete(route('fees.setup.types.destroy', $type))->assertSessionHas('success');
        $this->assertSoftDeleted('fee_types', ['id' => $type->id]);
        $student = $this->feeStudent();
        $used = $this->feeType($student, 'Used');
        $this->delete(route('fees.setup.types.destroy', $used))->assertSessionHas('error');
        $voucherType = $this->feeType($student, 'Voucher fee');
        app(\App\Services\FeeBillingService::class)->generate($student, 10, 2026, auth()->user());
        \App\Models\FeeStructure::where('fee_type_id', $voucherType->id)->delete();
        $this->delete(route('fees.setup.types.destroy', $voucherType))->assertSessionHas('error');
    }

    public function test_structure_save_is_atomic_copy_preserves_inactive_and_monthly_totals(): void
    {
        $this->admin();
        $student = $this->feeStudent();
        $tuition = $this->feeType($student);
        $exam = $this->feeType($student, 'Exam', 'quarterly', 500);
        $data = ['academic_session_id' => $student->academic_session_id, 'school_class_id' => $student->class_id, 'amounts' => [$tuition->id => 1500, $exam->id => 750]];
        $this->post(route('fees.setup.structures.store'), $data)->assertSessionHasNoErrors();
        $bad = $data;
        $bad['amounts'][$exam->id] = -1;
        $bad['amounts'][$tuition->id] = 999;
        $this->post(route('fees.setup.structures.store'), $bad)->assertSessionHasErrors();
        $this->assertDatabaseHas('fee_structures', ['fee_type_id' => $tuition->id, 'amount' => 1500]);
        unset($bad['amounts'][$exam->id]);
        $this->post(route('fees.setup.structures.store'), $bad)->assertSessionHasErrors('amounts');
        $target = SchoolClass::create(['name' => 'Class 2']);
        $session = AcademicSession::create(['name' => '2027-28']);
        $copy = ['academic_session_id' => $session->id, 'school_class_id' => $target->id, 'from_academic_session_id' => $student->academic_session_id, 'from_school_class_id' => $student->class_id];
        $this->post(route('fees.setup.structures.copy'), $copy)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fee_structures', ['school_class_id' => $target->id, 'academic_session_id' => $session->id, 'fee_type_id' => $tuition->id, 'amount' => 1500]);
        $this->get(route('fees.setup.structures.index', ['academic_session_id' => $session->id, 'school_class_id' => $target->id]))->assertOk()->assertSee('1,500.00');
        $this->assertDatabaseHas('activities', ['type' => 'fee_structure']);
        $exam->update(['is_active' => false]);
        $this->post(route('fees.setup.structures.copy'), $copy)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fee_structures', ['school_class_id' => $target->id, 'fee_type_id' => $exam->id, 'amount' => 750]);
        $copy['from_academic_session_id'] = $session->id;
        $copy['from_school_class_id'] = $target->id;
        $this->post(route('fees.setup.structures.copy'), $copy)->assertSessionHasErrors('from_school_class_id');
    }

    public function test_discount_crud_assignment_overlap_dates_and_lookup(): void
    {
        $this->admin();
        $student = $this->feeStudent();
        $type = $this->feeType($student);
        $data = ['name' => 'Merit', 'type' => 'percent', 'value' => 10, 'is_active' => 1];
        $this->post(route('fees.setup.discounts.store'), array_replace($data, ['value' => 101]))->assertSessionHasErrors('value');
        $this->post(route('fees.setup.discounts.store'), $data)->assertSessionHasNoErrors();
        $discount = Discount::firstOrFail();
        $this->get(route('fees.setup.discounts.edit', $discount))->assertOk();
        $this->put(route('fees.setup.discounts.update', $discount), array_replace($data, ['value' => 15]))->assertSessionHasNoErrors();
        $assignment = ['student_id' => $student->id, 'discount_id' => $discount->id, 'fee_type_id' => $type->id, 'valid_from' => '2026-10-01', 'valid_to' => '2026-10-31'];
        $this->post(route('fees.setup.student-discounts.store'), $assignment)->assertSessionHasNoErrors();
        $this->post(route('fees.setup.student-discounts.store'), array_replace($assignment, ['fee_type_id' => null]))->assertSessionHasErrors('discount_id');
        $this->post(route('fees.setup.student-discounts.store'), array_replace($assignment, ['valid_to' => '2026-09-01']))->assertSessionHasErrors('valid_to');
        $this->delete(route('fees.setup.discounts.destroy', $discount))->assertSessionHas('error');
        $this->getJson(route('fees.setup.students.search', ['q' => 'Ali Khan']))->assertOk()->assertJsonPath('0.id', $student->id);
        $this->get(route('fees.setup.discounts.index'))->assertOk()->assertSee('Merit')->assertSee($student->admission_no);
        $this->delete(route('fees.setup.student-discounts.destroy', StudentDiscount::firstOrFail()))->assertSessionHasNoErrors();
        $this->delete(route('fees.setup.discounts.destroy', $discount))->assertSessionHas('success');
        $this->assertDatabaseHas('activities', ['type' => 'discount']);
    }

    public function test_student_override_sections_and_ownership(): void
    {
        $this->admin();
        $student = $this->feeStudent();
        $type = $this->feeType($student);
        $other = $this->feeStudent(['first_name' => 'Other']);
        $data = ['fee_type_id' => $type->id, 'amount' => 700, 'reason' => 'Approved concession'];
        $this->post(route('fees.overrides.store', $student), $data)->assertSessionHasNoErrors();
        $override = $student->feeOverrides()->firstOrFail();
        $this->get(route('students.edit', $student))->assertOk()->assertSee('Student Fee Setup')->assertSee('Approved concession');
        $this->get(route('students.show', $student))->assertOk()->assertSee('700.00');
        $this->delete(route('fees.overrides.destroy', [$other, $override->id]))->assertNotFound();
        $this->post(route('fees.overrides.store', $student), array_replace($data, ['amount' => 0]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('student_fee_overrides', 1);
        $this->delete(route('fees.overrides.destroy', [$student, $override->id]))->assertSessionHas('success');
        $this->actingAs(User::factory()->create(['role' => 'Teacher']))->post(route('fees.overrides.store', $student), $data)->assertForbidden();
    }

    public function test_settings_defaults_validation_and_singleton(): void
    {
        $this->admin();
        $settings = FeeSetting::findOrFail(1);
        $this->assertSame(10, $settings->due_day);
        $data = $settings->only($settings->getFillable());
        $data['voucher_prefix'] = 'vch';
        $data['receipt_prefix'] = 'rcp';
        $data['sibling_discount_enabled'] = true;
        $data['sibling_discount_value'] = 10;
        $this->put(route('fees.setup.settings.update'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('fee_settings', 1);
        $this->assertDatabaseHas('fee_settings', ['voucher_prefix' => 'VCH']);
        $this->put(route('fees.setup.settings.update'), array_replace($data, ['sibling_discount_value' => 101]))->assertSessionHasErrors('sibling_discount_value');
        $this->put(route('fees.setup.settings.update'), array_replace($data, ['due_day' => 32, 'fine_amount' => -1, 'receipt_prefix' => 'invalid prefix']))->assertSessionHasErrors(['due_day', 'fine_amount', 'receipt_prefix']);
    }

    public function test_seeder_is_repeatable_and_preserves_custom_amounts(): void
    {
        $student = $this->feeStudent();
        $last = SchoolClass::create(['name' => 'Class 10']);
        $this->seed(FeeSetupSeeder::class);
        $this->seed(FeeSetupSeeder::class);
        $this->assertDatabaseCount('fee_types', 6);
        $this->assertDatabaseCount('fee_structures', 4);
        $tuition = FeeType::where('name', 'Tuition Fee')->firstOrFail();
        $this->assertDatabaseHas('fee_structures', ['school_class_id' => $student->class_id, 'fee_type_id' => $tuition->id, 'amount' => 1500]);
        $this->assertDatabaseHas('fee_structures', ['school_class_id' => $last->id, 'fee_type_id' => $tuition->id, 'amount' => 3500]);
        FeeStructure::where('school_class_id',$student->class_id)->where('fee_type_id',$tuition->id)->update(['amount' => 1234]);
        $this->seed(FeeSetupSeeder::class);
        $this->assertDatabaseHas('fee_structures',['school_class_id' => $student->class_id, 'fee_type_id' => $tuition->id, 'amount' => 1234]);
    }
}

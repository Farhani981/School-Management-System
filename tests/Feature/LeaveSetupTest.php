<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Designation;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveSetupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Teacher $teacher;

    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($this->admin);
        $this->session = AcademicSession::create(['name' => '2026-2027', 'is_current' => true]);
        $this->teacher = Teacher::create(['name' => 'Test Teacher', 'employee_id' => 'TEST-1', 'cnic' => '12345-1234567-1', 'phone' => '03001234567', 'gender' => 'Female', 'department_id' => Department::create(['name' => 'Science'])->id, 'designation_id' => Designation::create(['name' => 'Teacher'])->id, 'joining_date' => '2026-01-01', 'status' => 'Active']);
    }

    private function initialize(): void
    {
        $this->post(route('leaves.balances.initialize'), ['session_id' => $this->session->id])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_admin_pages_render_and_teacher_cannot_access_setup(): void
    {
        $this->get(route('leaves.types.index'))->assertOk()->assertSee('Emergency');
        $this->get(route('leaves.balances.index'))->assertOk()->assertSee('No balances');
        $this->actingAs(User::factory()->create(['role' => 'Teacher']));
        $this->get(route('leaves.types.index'))->assertForbidden();
        $this->get(route('leaves.balances.index'))->assertForbidden();
        $this->post(route('leaves.balances.initialize'), ['session_id' => $this->session->id])->assertForbidden();
        $this->post(route('leaves.types.store'), ['name' => 'Unauthorized'])->assertForbidden();
    }

    public function test_decimal_quota_and_settings_are_editable_without_changing_existing_totals(): void
    {
        $this->initialize();
        $type = LeaveType::where('name', 'Casual')->firstOrFail();
        $this->put(route('leaves.types.update', $type), ['name' => 'Casual', 'annual_quota' => 15.5, 'min_notice_days' => 2, 'max_consecutive_days' => 5, 'is_paid' => 1, 'allow_half_day' => 1, 'requires_attachment' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_types', ['id' => $type->id, 'annual_quota' => 15.5, 'is_active' => false, 'allow_half_day' => true]);
        $this->assertDatabaseHas('leave_balances', ['leave_type_id' => $type->id, 'total' => 12]);
        $this->delete('/leaves/types/'.$type->id)->assertStatus(405);
    }

    public function test_initialization_is_idempotent_and_session_specific(): void
    {
        $this->initialize();
        $count = LeaveBalance::count();
        $this->initialize();
        $this->assertSame($count, LeaveBalance::count());
        $this->assertSame($count, DB::table('leave_balance_logs')->count());
        $other = AcademicSession::create(['name' => '2027-2028']);
        $this->post(route('leaves.balances.initialize'), ['session_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame($count * 2, LeaveBalance::count());
    }

    public function test_pending_is_reserved_and_adjustments_require_reason_and_are_logged(): void
    {
        $this->initialize();
        $balance = LeaveBalance::where('leave_type_id', LeaveType::where('name', 'Casual')->value('id'))->firstOrFail();
        $balance->update(['used' => 3]);
        LeaveRequest::create(['teacher_id' => $this->teacher->id, 'leave_type_id' => $balance->leave_type_id, 'session_id' => $this->session->id, 'start_date' => '2026-10-12', 'end_date' => '2026-10-12', 'days' => 0.5, 'reason' => 'Appointment', 'status' => 'pending']);
        $this->get(route('leaves.balances.index'))->assertOk()->assertSee('8.50')->assertSee('0.50');
        $this->put(route('leaves.balances.adjust', $balance), ['total' => 15])->assertSessionHasErrors('reason');
        $this->put(route('leaves.balances.adjust', $balance), ['total' => 3, 'reason' => 'Too low'])->assertSessionHasErrors('total');
        $this->put(route('leaves.balances.adjust', $balance), ['total' => 15, 'reason' => 'Entitlement correction'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_balances', ['id' => $balance->id, 'total' => 15, 'used' => 3]);
        $this->assertDatabaseHas('leave_balance_logs', ['balance_id' => $balance->id, 'reason' => 'Entitlement correction', 'old_total' => 12, 'new_total' => 15, 'actor_id' => $this->admin->id]);
        $this->actingAs(User::factory()->create(['role' => 'Teacher']));
        $this->put(route('leaves.balances.adjust', $balance), ['total' => 100, 'reason' => 'Unauthorized'])->assertForbidden();
        $this->put(route('leaves.types.update', $balance->leave_type_id), [])->assertForbidden();
    }

    public function test_carry_forward_excludes_used_and_pending_and_preserves_source(): void
    {
        $this->initialize();
        $balance = LeaveBalance::where('leave_type_id', LeaveType::where('name', 'Casual')->value('id'))->firstOrFail();
        $balance->update(['used' => 3]);
        LeaveRequest::create(['teacher_id' => $this->teacher->id, 'leave_type_id' => $balance->leave_type_id, 'session_id' => $this->session->id, 'start_date' => '2026-10-12', 'end_date' => '2026-10-12', 'days' => 1, 'reason' => 'Appointment', 'status' => 'Pending']);
        $other = AcademicSession::create(['name' => '2027-2028']);
        $this->post(route('leaves.balances.initialize'), ['session_id' => $other->id, 'source_session_id' => $this->session->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_balances', ['session_id' => $other->id, 'leave_type_id' => $balance->leave_type_id, 'total' => 20, 'used' => 0]);
        $this->assertSame('12.00', $balance->fresh()->total);
        $this->post(route('leaves.balances.initialize'), ['session_id' => $other->id, 'source_session_id' => $other->id])->assertSessionHasErrors('source_session_id');
    }
}

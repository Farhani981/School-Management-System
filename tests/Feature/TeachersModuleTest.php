<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\TeacherRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeachersModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
        Department::create(['name' => 'Science']);
        Designation::create(['name' => 'Lecturer']);
        foreach (['school_classes', 'school_sections', 'academic_sessions', 'subjects'] as $table) {
            DB::table($table)->insert(['name' => 'Option 1']);
        }
    }

    private function details(array $extra = []): array
    {
        return array_merge(['name' => 'Ayesha Khan', 'cnic' => '12345-1234567-1', 'gender' => 'Female', 'phone' => '03001234567', 'department_id' => 1, 'designation_id' => 1, 'joining_date' => '2026-01-01', 'status' => 'Active', 'basic_salary' => 31000, 'other_allowance' => 2000, 'fixed_deductions' => 1000], $extra);
    }

    private function teacher(array $extra = []): Teacher
    {
        return app(TeacherRecords::class)->save($this->details($extra));
    }

    public function test_inline_teacher_setup_creates_and_reuses_options(): void
    {
        $this->get(route('teachers.create'))->assertOk()->assertSee('Add new department')->assertSee('Add new designation');
        $this->postJson(route('teachers.setup'), ['type' => 'department', 'name' => ' Mathematics '])
            ->assertCreated()->assertJson(['name' => 'Mathematics']);
        $department = Department::where('name', 'Mathematics')->firstOrFail();
        $this->postJson(route('teachers.setup'), ['type' => 'department', 'name' => 'Mathematics'])
            ->assertOk()->assertJson(['id' => $department->id]);
        $this->assertSame(1, Department::where('name', 'Mathematics')->count());
        $this->postJson(route('teachers.setup'), ['type' => 'designation', 'name' => 'Senior Teacher'])
            ->assertCreated()->assertJson(['name' => 'Senior Teacher']);
        $this->postJson(route('teachers.setup'), ['type' => 'department', 'name' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->post(route('teachers.store'), $this->details(['department_id' => $department->id, 'designation_id' => Designation::where('name', 'Senior Teacher')->value('id')]))
            ->assertSessionHasNoErrors();
        $this->get(route('teachers.edit', Teacher::firstOrFail()))->assertOk()->assertSee('Add new department');
    }

    public function test_uploaded_teacher_photo_is_rendered_with_same_origin_url(): void
    {
        Storage::fake('public');
        $path = 'teachers/photos/avatar.png';
        Storage::disk('public')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jvXkAAAAASUVORK5CYII='));
        $teacher = $this->teacher();
        $teacher->update(['photo' => $path]);
        $this->get('http://127.0.0.1:8011/teachers')->assertOk()->assertSee('src="/storage/'.$path.'"', false)->assertSee('data-teacher-avatar', false);
        $this->get(route('teachers.show', $teacher))->assertOk()->assertSee('src="/storage/'.$path.'"', false);
    }

    public function test_salary_components_defaults_bank_details_and_gross_validation(): void
    {
        $data = $this->details(['basic_salary' => 100, 'house_allowance' => 200, 'medical_allowance' => 50, 'transport_allowance' => 25, 'other_allowance' => null, 'fixed_deductions' => 300, 'bank_name' => 'School Bank', 'account_no' => '000012345']);
        $this->post(route('teachers.store'), $data)->assertSessionHasNoErrors();
        $teacher = Teacher::with('salaryStructure')->firstOrFail();
        $this->assertEquals(375, $teacher->salaryStructure->gross_salary);
        $this->assertEquals(75, $teacher->salaryStructure->net_salary);
        $this->assertSame('0.00', $teacher->salaryStructure->other_allowance);
        $this->assertSame('000012345', $teacher->salaryStructure->account_no);
        $this->get(route('teachers.show', $teacher))->assertOk()->assertSee('House Allowance')->assertSee('Rs. 375.00')->assertSee('000012345');
        $this->put(route('teachers.update', $teacher), array_merge($data, ['fixed_deductions' => 375.01]))->assertSessionHasErrors('fixed_deductions');
        $this->put(route('teachers.update', $teacher), array_merge($data, ['basic_salary' => 0]))->assertSessionHasErrors('basic_salary');
        $this->put(route('teachers.update', $teacher), array_merge($data, ['medical_allowance' => -1]))->assertSessionHasErrors('medical_allowance');
        $minimal = $this->details();
        unset($minimal['other_allowance'], $minimal['fixed_deductions']);
        $this->put(route('teachers.update', $teacher), $minimal)->assertSessionHasNoErrors();
        $this->assertEquals(31000, $teacher->salaryStructure->fresh()->net_salary);
        $this->assertSame('0.00', $teacher->salaryStructure->fresh()->fixed_deductions);
    }

    public function test_salary_migration_preserves_legacy_amounts(): void
    {
        $teacher = $this->teacher();
        DB::table('salary_structures')->where('teacher_id', $teacher->id)->update(['allowances' => 1234.56, 'deductions' => 789.12]);
        $migration = require database_path('migrations/2026_10_01_000500_expand_teacher_salary_structures.php');
        // Simulate the original schema before applying the new migration.
        Schema::table('salary_structures', fn ($table) => $table->dropColumn(['house_allowance', 'medical_allowance', 'transport_allowance', 'other_allowance', 'fixed_deductions', 'bank_name', 'account_no']));
        $migration->up();
        $salary = $teacher->salaryStructure()->firstOrFail();
        $this->assertSame('1234.56', $salary->other_allowance);
        $this->assertSame('789.12', $salary->fixed_deductions);
        $this->assertSame('0.00', $salary->house_allowance);
    }

    public function test_teacher_crud_login_and_private_documents(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->post(route('teachers.store'), $this->details(['create_login' => 1, 'login_email' => 'ayesha@example.test', 'password' => 'password123', 'password_confirmation' => 'password123', 'documents' => [UploadedFile::fake()->create('degree.pdf', 20, 'application/pdf')]]))->assertSessionHasNoErrors()->assertRedirect();
        $teacher = Teacher::with(['user', 'documents'])->firstOrFail();
        $this->assertMatchesRegularExpression('/^EMP-\d{4}-001$/', $teacher->employee_id);
        $this->assertSame('Teacher', $teacher->user->role);
        $this->assertTrue(Hash::check('password123', $teacher->user->password));
        Storage::disk('local')->assertExists($teacher->documents->first()->path);
        $this->get(route('teachers.documents.download', [$teacher, $teacher->documents->first()]))->assertOk();
        $this->put(route('teachers.update', $teacher), $this->details(['name' => 'Ayesha Ahmed', 'login_email' => 'ayesha@example.test']))->assertSessionHasNoErrors();
        $this->assertSame('Ayesha Ahmed', $teacher->user->fresh()->name);
        $this->delete(route('teachers.destroy', $teacher))->assertRedirect();
        $this->assertSoftDeleted($teacher);
        $this->assertDatabaseCount('teacher_documents', 1);
    }

    public function test_unique_cnic_format_and_employee_sequence(): void
    {
        $first = $this->teacher();
        $this->post(route('teachers.store'), $this->details())->assertSessionHasErrors('cnic');
        $this->post(route('teachers.store'), $this->details(['cnic' => 'invalid']))->assertSessionHasErrors('cnic');
        $first->delete();
        $second = $this->teacher(['cnic' => '12345-1234567-2']);
        $this->assertStringEndsWith('-002', $second->employee_id);
    }

    public function test_all_screens_render_and_directory_filters(): void
    {
        $teacher = $this->teacher();
        foreach (['teachers.index', 'teachers.create', 'teachers.assignments.index', 'teachers.leaves.index', 'teachers.payroll.index', 'teachers.import'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('teachers.show', $teacher))->assertOk()->assertSee('Assigned Classes');
        $this->get(route('teachers.edit', $teacher))->assertOk();
        $this->get(route('teachers.index', ['search' => $teacher->cnic, 'gender' => 'Female']))->assertSee('Ayesha Khan');
        $this->get(route('teachers.index', ['gender' => 'Male']))->assertDontSee('Ayesha Khan');
        $this->get(route('teachers.index', ['sort' => 'not_a_column']))->assertSessionHasErrors('sort');
    }

    public function test_assignment_subject_and_class_teacher_conflicts(): void
    {
        $teacher = $this->teacher();
        $other = $this->teacher(['name' => 'Ali', 'cnic' => '12345-1234567-2']);
        $data = ['teacher_id' => $teacher->id, 'class_id' => 1, 'section_id' => 1, 'subject_id' => 1, 'session_id' => 1, 'weekly_periods' => 5, 'is_class_teacher' => 1];
        $this->post(route('teachers.assignments.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('teachers.assignments.store'), array_merge($data, ['teacher_id' => $other->id]))->assertSessionHasErrors('subject_id');
        DB::table('subjects')->insert(['name' => 'Second']);
        $this->post(route('teachers.assignments.store'), array_merge($data, ['teacher_id' => $other->id, 'subject_id' => 2]))->assertSessionHasErrors('is_class_teacher');
        $this->assertDatabaseCount('teacher_assignments', 1);
        $assignment = TeacherAssignment::firstOrFail();
        $this->post(route('teachers.assignments.class-teacher', $assignment), ['is_class_teacher' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($assignment->fresh()->is_class_teacher);
        $this->post(route('teachers.assignments.class-teacher', $assignment), ['is_class_teacher' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($assignment->fresh()->is_class_teacher);
        $this->get(route('teachers.assignments.index'))->assertOk()->assertSee('5');
        $this->get(route('teachers.assignments.index', ['edit' => $assignment->id]))->assertOk()->assertSee('Save Changes')->assertSee('Edit Assignment');
        $this->put(route('teachers.assignments.update', $assignment), array_merge($data, ['teacher_id' => $other->id, 'weekly_periods' => 7]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('teacher_assignments', 1);
        $this->assertEquals($other->id, $assignment->fresh()->teacher_id);
        $this->assertEquals(7, $assignment->fresh()->weekly_periods);
        $this->post(route('teachers.assignments.store'), array_merge($data, ['subject_id' => 2, 'is_class_teacher' => 0]))->assertSessionHasNoErrors();
        $this->put(route('teachers.assignments.update', $assignment), array_merge($data, ['subject_id' => 2]))->assertSessionHasErrors('subject_id');
        $this->assertEquals(1, $assignment->fresh()->subject_id);
    }

    public function test_leave_balance_overlap_review_and_calendar(): void
    {
        $teacher = $this->teacher();
        $data = ['teacher_id' => $teacher->id, 'leave_type_id' => 1, 'start_date' => '2026-10-05', 'end_date' => '2026-10-07', 'reason' => 'Family'];
        $this->post(route('teachers.leaves.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('teachers.leaves.store'), $data)->assertSessionHasErrors('start_date');
        $this->post(route('teachers.leaves.store'), array_merge($data, ['start_date' => '2026-11-01', 'end_date' => '2026-11-20']))->assertSessionHasErrors('end_date');
        $leave = LeaveRequest::firstOrFail();
        $this->post(route('teachers.leaves.review', $leave), ['status' => 'Approved', 'remarks' => 'Approved cover'])->assertSessionHasNoErrors();
        $this->post(route('teachers.leaves.review', $leave), ['status' => 'Rejected', 'remarks' => 'Again'])->assertSessionHasErrors('status');
        $this->get(route('teachers.leaves.index', ['month' => '2026-10', 'teacher_id' => $teacher->id, 'view' => 'calendar']))->assertOk()->assertSee('9 / 12 days remaining')->assertSee('Ayesha Khan');
    }

    public function test_payroll_paid_leave_unpaid_leave_advances_and_idempotency(): void
    {
        $teacher = $this->teacher();
        $teacher->attendances()->createMany([['date' => '2026-10-01', 'status' => 'Absent'], ['date' => '2026-10-02', 'status' => 'Absent'], ['date' => '2026-10-03', 'status' => 'Leave']]);
        $teacher->leaves()->create(['leave_type_id' => 1, 'start_date' => '2026-10-01', 'end_date' => '2026-10-01', 'reason' => 'Paid', 'status' => 'Approved']);
        $teacher->leaves()->create(['leave_type_id' => 3, 'start_date' => '2026-10-03', 'end_date' => '2026-10-04', 'reason' => 'Unpaid', 'status' => 'Approved']);
        $teacher->advances()->create(['month' => '2026-10-01', 'amount' => 3000]);
        $this->post(route('teachers.payroll.generate'), ['month' => '2026-10'])->assertSessionHasNoErrors();
        $payroll = Payroll::firstOrFail();
        $this->assertEquals(3, $payroll->unpaid_days);
        $this->assertEquals(3000, $payroll->absence_deduction);
        $this->assertEquals(26000, $payroll->net_salary);
        $this->post(route('teachers.payroll.generate'), ['month' => '2026-10'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payrolls', 1);
        $this->post(route('teachers.payroll.paid', $payroll))->assertSessionHasNoErrors();
        $this->assertSame('Paid', $payroll->fresh()->status);
        $this->get(route('teachers.payroll.slip', $payroll))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('teachers.payroll.report', ['month' => '2026-10']))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
        $this->post(route('teachers.payroll.attendance'), ['teacher_id' => $teacher->id, 'date' => '2026-10-01', 'status' => 'Absent'])->assertSessionHasErrors('month');
        $this->post(route('teachers.payroll.advances'), ['teacher_id' => $teacher->id, 'month' => '2026-10', 'amount' => 100])->assertSessionHasErrors('month');
    }

    public function test_payroll_batch_rolls_back_if_a_teacher_has_excessive_deductions(): void
    {
        $this->teacher();
        $other = $this->teacher(['cnic' => '12345-1234567-2']);
        $other->advances()->create(['month' => '2026-10-01', 'amount' => 90000]);
        $this->post(route('teachers.payroll.generate'), ['month' => '2026-10'])->assertSessionHasErrors('month');
        $this->assertDatabaseCount('payrolls', 0);
    }

    public function test_import_atomicity_and_exports(): void
    {
        $header = "name,cnic,gender,phone,email,department,designation,joining_date,status,basic_salary,allowances,deductions\n";
        $row = "Ayesha Khan,12345-1234567-1,Female,03001234567,,Science,Lecturer,2026-01-01,Active,31000,2000,1000\n";
        $file = UploadedFile::fake()->createWithContent('teachers.csv', $header.$row.$row);
        $this->post(route('teachers.import.store'), ['file' => $file])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('teachers', 0);
        $file = UploadedFile::fake()->createWithContent('teachers.csv', $header.$row);
        $this->post(route('teachers.import.store'), ['file' => $file])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('teachers', 1);
        $this->get(route('teachers.export', 'xlsx'))->assertOk();
        $this->get(route('teachers.export', 'pdf'))->assertOk();
    }

    public function test_payroll_requires_pending_leaves_to_be_reviewed_and_prorates_new_joiners(): void
    {
        $teacher = $this->teacher(['joining_date' => '2026-10-16', 'other_allowance' => 0, 'fixed_deductions' => 0]);
        $leave = $teacher->leaves()->create(['leave_type_id' => 1, 'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'reason' => 'Appointment']);
        $this->post(route('teachers.payroll.generate'), ['month' => '2026-10'])->assertSessionHasErrors('month');
        $this->assertDatabaseCount('payrolls', 0);
        $this->post(route('teachers.leaves.review', $leave), ['status' => 'Approved', 'remarks' => 'Approved'])->assertSessionHasNoErrors();
        $this->post(route('teachers.payroll.generate'), ['month' => '2026-10'])->assertSessionHasNoErrors();
        $this->assertEquals(16000, Payroll::firstOrFail()->net_salary);
    }
}

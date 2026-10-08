<?php

namespace Tests\Feature;

use App\Jobs\SendAbsenceNotification;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Holiday;
use App\Models\Payroll;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\AttendanceReports;
use App\Services\AttendanceStats;
use App\Services\TeacherPayroll;
use App\Services\TeacherRecords;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AttendanceModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
        $this->admin = User::factory()->create(['role' => 'Admin']);
        SchoolClass::create(['name' => 'Class 1']);
        SchoolClass::create(['name' => 'Class 2']);
        SchoolSection::create(['name' => 'A']);
        AcademicSession::create(['name' => '2026-2027']);
        Department::create(['name' => 'Science']);
        Designation::create(['name' => 'Lecturer']);
        Subject::create(['name' => 'English']);
    }

    private function student(array $data = []): Student
    {
        $number = Student::withTrashed()->count() + 1;

        return Student::create(array_merge(['first_name' => 'Student '.$number, 'last_name' => 'Khan', 'father_name' => 'Ahmed', 'admission_no' => 'ATT-'.$number, 'roll_number' => (string) $number, 'date_of_birth' => '2015-01-01', 'gender' => 'Male', 'phone' => '03001234567', 'address' => 'School Road', 'admission_date' => '2026-01-01', 'status' => 'Active', 'class_id' => 1, 'section_id' => 1, 'academic_session_id' => 1], $data));
    }

    private function teacher(array $data = []): Teacher
    {
        $number = Teacher::count() + 1;

        return app(TeacherRecords::class)->save(array_merge(['name' => 'Teacher '.$number, 'cnic' => '12345-1234567-'.$number, 'gender' => 'Male', 'phone' => '03001234567', 'department_id' => 1, 'designation_id' => 1, 'joining_date' => '2026-01-01', 'status' => 'Active', 'basic_salary' => 31000], $data));
    }

    private function batch($students, array $extra = []): array
    {
        return array_merge(['date' => '2026-10-01', 'school_class_id' => 1, 'section_id' => 1, 'academic_session_id' => 1, 'rows' => collect($students)->map(fn ($s) => ['student_id' => $s->id, 'status' => 'present', 'remarks' => ''])->all()], $extra);
    }

    public function test_sign_in_roles_guest_redirect_and_screens(): void
    {
        $this->get(route('attendance.students.index'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();
        $this->post(route('login.store'), ['email' => $this->admin->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->admin);
        foreach (['attendance.students.index', 'attendance.staff.index', 'attendance.reports.index', 'attendance.holidays.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
        $this->actingAs(User::factory()->create(['role' => 'User']))->get(route('attendance.reports.index'))->assertForbidden();
    }

    public function test_student_upsert_summary_order_future_and_roster_protection(): void
    {
        $this->actingAs($this->admin);
        $ten = $this->student(['roll_number' => '10']);
        $two = $this->student(['roll_number' => '2']);
        $data = $this->batch([$ten, $two]);
        $data['rows'][1]['status'] = 'half_day';
        $this->post(route('attendance.students.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_attendances', ['student_id' => $two->id, 'status' => 'half_day', 'marked_by' => $this->admin->id]);
        $data['rows'][0]['status'] = 'late';
        $this->post(route('attendance.students.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('student_attendances', 2);
        $view = $this->get(route('attendance.students.index', collect($data)->except('rows')->all()))->assertOk()->assertSee('already marked');
        $this->assertSame([$two->id, $ten->id], $view['students']->pluck('id')->all());
        $this->post(route('attendance.students.store'), $this->batch([$ten, $two], ['date' => '2026-10-02']))->assertSessionHasErrors('date');
        $this->post(route('attendance.students.store'), $this->batch([$ten]))->assertSessionHasErrors('rows');
        $outside = $this->student(['class_id' => 2]);
        $this->post(route('attendance.students.store'), $this->batch([$ten, $outside]))->assertSessionHasErrors('rows');
        $stats = app(AttendanceStats::class)->today();
        $this->assertSame(1, $stats['unmarked']);
        $this->assertSame(1, $stats['counts']['half_day']);
    }

    public function test_teacher_policy_and_report_export_scope(): void
    {
        $teacher = $this->teacher();
        $user = User::factory()->create(['role' => 'Teacher']);
        $teacher->update(['user_id' => $user->id]);
        $assignment = TeacherAssignment::create(['teacher_id' => $teacher->id, 'class_id' => 1, 'section_id' => 1, 'session_id' => 1, 'subject_id' => 1, 'weekly_periods' => 1]);
        $own = $this->student(['first_name' => 'Allowed Student']);
        $other = $this->student(['first_name' => 'Private Student', 'class_id' => 2]);
        $this->actingAs($this->admin)->post(route('attendance.students.store'), $this->batch([$other], ['school_class_id' => 2]))->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('teacher.attendance.store', $assignment), $this->batch([$own]))->assertSessionHasNoErrors();
        $this->get(route('attendance.students.index', ['date' => '2026-10-01', 'school_class_id' => 2, 'section_id' => 1, 'academic_session_id' => 1]))->assertForbidden();
        $this->post(route('attendance.students.store'), $this->batch([$other], ['school_class_id' => 2]))->assertForbidden();
        $this->get(route('attendance.staff.index'))->assertForbidden();
        $this->get(route('teacher.attendance.register', $assignment))->assertOk()->assertSee('Allowed Student')->assertDontSee('Private Student');
        $this->get(route('attendance.reports.index'))->assertForbidden();
        $this->get(route('attendance.reports.export', 'xlsx'))->assertForbidden();
        $this->get(route('attendance.reports.export', 'pdf'))->assertForbidden();
    }

    public function test_holidays_crud_warning_and_queued_absence_only_on_change(): void
    {
        Queue::fake();
        $this->actingAs($this->admin);
        $student = $this->student();
        $this->post(route('attendance.holidays.store'), ['name' => 'School Closure', 'start_date' => '2026-10-01', 'end_date' => '2026-10-01'])->assertSessionHasNoErrors();
        $data = $this->batch([$student], ['notify_absent' => 1]);
        $data['rows'][0]['status'] = 'absent';
        $this->get(route('attendance.students.index', collect($data)->except('rows')->all()))->assertSee('Holiday: School Closure');
        $this->post(route('attendance.students.store'), $data)->assertSessionHasNoErrors();
        Queue::assertPushed(SendAbsenceNotification::class, 1);
        $this->post(route('attendance.students.store'), $data)->assertSessionHasNoErrors();
        Queue::assertPushed(SendAbsenceNotification::class, 1);
        $holiday = Holiday::firstOrFail();
        $this->put(route('attendance.holidays.update', $holiday), ['name' => 'Updated Closure', 'start_date' => '2026-10-01', 'end_date' => '2026-10-02'])->assertSessionHasNoErrors();
        $this->delete(route('attendance.holidays.destroy', $holiday))->assertSessionHas('success');
        $this->get(route('attendance.students.index', ['date' => '2026-09-27']))->assertSee('Sunday');
    }

    public function test_staff_approved_leave_lateness_and_payroll_lock(): void
    {
        $this->actingAs($this->admin);
        $teacher = $this->teacher();
        $onLeave = $this->teacher();
        $onLeave->leaves()->create(['leave_type_id' => 1, 'start_date' => '2026-10-01', 'end_date' => '2026-10-01', 'reason' => 'Sick', 'status' => 'Approved']);
        $data = ['date' => '2026-10-01', 'rows' => [['teacher_id' => $teacher->id, 'status' => 'present', 'check_in' => '08:15', 'check_out' => '14:00'], ['teacher_id' => $onLeave->id, 'status' => 'absent']]];
        $this->post(route('attendance.staff.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('staff_attendances', ['teacher_id' => $teacher->id, 'status' => 'late', 'late_minutes' => 15]);
        $this->assertDatabaseHas('staff_attendances', ['teacher_id' => $onLeave->id, 'status' => 'leave']);
        $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $onLeave->id, 'status' => 'Leave']);
        $this->post(route('attendance.staff.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('staff_attendances', 2);
        $data['rows'][0]['status'] = 'half_day';
        $this->post(route('attendance.staff.store'), $data)->assertSessionHasNoErrors();
        app(TeacherPayroll::class)->generate('2026-10');
        $payroll = Payroll::where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertEquals(0.5, $payroll->unpaid_days);
        $this->assertEquals(500, $payroll->absence_deduction);
        $this->post(route('attendance.staff.store'), $data)->assertSessionHasErrors('month');
    }

    public function test_percentages_half_days_leave_holiday_and_profile_calendar(): void
    {
        $student = $this->student();
        foreach (['2026-09-28' => 'present', '2026-09-29' => 'absent', '2026-09-30' => 'half_day', '2026-10-01' => 'leave'] as $date => $status) {
            $student->attendances()->create(['date' => $date, 'status' => $status, 'school_class_id' => 1, 'section_id' => 1, 'academic_session_id' => 1]);
        }
        $service = app(AttendanceReports::class);
        $report = $service->build(['from' => '2026-09-28', 'to' => '2026-10-01'], $this->admin);
        $this->assertEquals(50, $report['register']->first()['percentage']);
        $this->assertCount(1, $report['low']);
        Holiday::create(['name' => 'Closure', 'start_date' => '2026-09-29', 'end_date' => '2026-09-29']);
        $report = $service->build(['from' => '2026-09-28', 'to' => '2026-10-01'], $this->admin);
        $this->assertEquals(75, $report['register']->first()['percentage']);
        $this->actingAs($this->admin)->get(route('students.show', [$student, 'attendance_month' => '2026-09']))->assertOk()->assertSee('75%')->assertSee('Closure');
        $this->get(route('attendance.reports.index', ['from' => '2026-09-28', 'to' => '2026-10-01']))->assertOk()->assertSee('Monthly Register');
        $this->get(route('attendance.reports.export', ['format' => 'pdf', 'from' => '2026-09-28', 'to' => '2026-10-01']))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_legacy_student_attendance_is_kept_and_dashboard_handles_zero(): void
    {
        $this->actingAs($this->admin);
        $this->get('/')->assertOk()->assertSee('View Full Attendance')
            ->assertViewHas('attendanceStats',fn($stats)=>(int)$stats['total']===0 && is_finite((float)$stats['percentage']));
        $student = $this->student();
        $student->attendances()->create(['attendance_date' => '2026-10-01', 'status' => 'Present']);
        $this->assertDatabaseHas('student_attendances', ['student_id' => $student->id, 'date' => '2026-10-01', 'status' => 'present']);
        $this->actingAs($this->admin)->post(route('attendance.students.store'), $this->batch([$student]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('student_attendances',1);
        $this->assertSame(1,app(AttendanceStats::class)->today()['marked']);
    }
}

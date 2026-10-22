<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Holiday;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentLeaveRequest;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentAttendanceAndLeaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Parent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Teacher', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    }

    private function createStudentWithContext(string $admissionNo = 'STD-2026-001'): array
    {
        $user = User::factory()->create([
            'name' => 'Test Student',
            'email' => "student_{$admissionNo}@test.com",
            'role' => 'Student',
        ]);
        $user->assignRole('Student');

        $session = AcademicSession::firstOrCreate(['name' => '2026-2027'], ['is_current' => true]);
        $class = SchoolClass::firstOrCreate(['name' => 'Grade 10']);
        $section = SchoolSection::firstOrCreate(['name' => 'Section A']);

        $student = Student::create([
            'user_id' => $user->id,
            'first_name' => 'Hamza',
            'last_name' => 'Ali',
            'father_name' => 'Tariq Ali',
            'phone' => '03001234567',
            'gender' => 'Male',
            'address' => 'Test Address',
            'date_of_birth' => '2010-05-15',
            'admission_date' => '2026-01-10',
            'admission_no' => $admissionNo,
            'roll_number' => (string) rand(100, 99999),
            'class_id' => $class->id,
            'section_id' => $section->id,
            'academic_session_id' => $session->id,
            'status' => 'Active',
        ]);

        return compact('user', 'student', 'class', 'section', 'session');
    }

    private function createTeacher(User $user, string $name = 'Teacher'): Teacher
    {
        $dept = \App\Models\Department::firstOrCreate(['name' => 'Science']);
        $desig = \App\Models\Designation::firstOrCreate(['name' => 'Teacher']);

        return Teacher::create([
            'user_id' => $user->id,
            'name' => $name,
            'employee_id' => 'EMP-'.rand(10000, 99999),
            'cnic' => '35201-'.rand(1000000, 9999999).'-1',
            'phone' => '03001234567',
            'gender' => 'Male',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'joining_date' => '2026-01-01',
            'status' => 'Active',
        ]);
    }

    // =========================================================================
    // TASK 1: Attendance APIs
    // =========================================================================

    public function test_get_attendance_api_defaults_to_current_month(): void
    {
        $ctx = $this->createStudentWithContext();
        $student = $ctx['student'];
        $currentMonth = now()->format('Y-m');

        $response = $this->actingAs($ctx['user'])
            ->getJson('/student/attendance');

        $response->assertOk()
            ->assertJsonStructure([
                'month',
                'days' => [
                    '*' => ['date', 'status', 'holiday_name', 'remarks'],
                ],
                'summary' => [
                    'totalWorkingDays',
                    'present',
                    'absent',
                    'late',
                    'leave',
                    'percentage',
                ],
            ]);

        $this->assertEquals($currentMonth, $response->json('month'));
    }

    public function test_get_attendance_api_calculates_percentage_and_statuses_accurately(): void
    {
        $ctx = $this->createStudentWithContext();
        $student = $ctx['student'];
        $month = now()->format('Y-m');

        // Create 2 present days, 1 late day, 1 absent day, 1 leave day on working days
        // Pick a Monday in the current month
        $currentMonthStart = CarbonImmutable::now()->startOfMonth();
        $firstMonday = $currentMonthStart->next(Carbon::MONDAY);
        $tuesday = $firstMonday->addDay();
        $wednesday = $firstMonday->addDays(2);
        $thursday = $firstMonday->addDays(3);
        $friday = $firstMonday->addDays(4);

        StudentAttendance::create([
            'student_id' => $student->id,
            'date' => $firstMonday->toDateString(),
            'attendance_date' => $firstMonday->toDateString(),
            'status' => 'present',
            'school_class_id' => $student->class_id,
            'section_id' => $student->section_id,
            'academic_session_id' => $student->academic_session_id,
        ]);

        StudentAttendance::create([
            'student_id' => $student->id,
            'date' => $tuesday->toDateString(),
            'attendance_date' => $tuesday->toDateString(),
            'status' => 'present',
            'school_class_id' => $student->class_id,
            'section_id' => $student->section_id,
            'academic_session_id' => $student->academic_session_id,
        ]);

        StudentAttendance::create([
            'student_id' => $student->id,
            'date' => $wednesday->toDateString(),
            'attendance_date' => $wednesday->toDateString(),
            'status' => 'late',
            'school_class_id' => $student->class_id,
            'section_id' => $student->section_id,
            'academic_session_id' => $student->academic_session_id,
        ]);

        StudentAttendance::create([
            'student_id' => $student->id,
            'date' => $thursday->toDateString(),
            'attendance_date' => $thursday->toDateString(),
            'status' => 'absent',
            'school_class_id' => $student->class_id,
            'section_id' => $student->section_id,
            'academic_session_id' => $student->academic_session_id,
        ]);

        StudentAttendance::create([
            'student_id' => $student->id,
            'date' => $friday->toDateString(),
            'attendance_date' => $friday->toDateString(),
            'status' => 'leave',
            'school_class_id' => $student->class_id,
            'section_id' => $student->section_id,
            'academic_session_id' => $student->academic_session_id,
        ]);

        $response = $this->actingAs($ctx['user'])
            ->getJson("/student/attendance?month={$month}");

        $response->assertOk();
        $summary = $response->json('summary');

        // Total working days recorded = 5 (2 present, 1 late, 1 absent, 1 leave)
        $this->assertEquals(5, $summary['totalWorkingDays']);
        $this->assertEquals(2, $summary['present']);
        $this->assertEquals(1, $summary['late']);
        $this->assertEquals(1, $summary['absent']);
        $this->assertEquals(1, $summary['leave']);

        // Percentage = (present + late) / totalWorkingDays * 100 = (2 + 1) / 5 * 100 = 60.0%
        $this->assertEquals(60.0, $summary['percentage']);

        // Check days array contains valid status values
        $days = $response->json('days');
        $validStatuses = ['present', 'absent', 'late', 'leave', 'holiday', 'weekend', 'none'];
        foreach ($days as $day) {
            $this->assertContains($day['status'], $validStatuses);
        }
    }

    public function test_get_attendance_api_rejects_future_month_and_invalid_format(): void
    {
        $ctx = $this->createStudentWithContext();

        // 1. Future month rejection
        $futureMonth = now()->addMonths(2)->format('Y-m');
        $response = $this->actingAs($ctx['user'])
            ->getJson("/student/attendance?month={$futureMonth}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['month']);

        // 2. Invalid format rejection
        $response2 = $this->actingAs($ctx['user'])
            ->getJson('/student/attendance?month=2026-13');

        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['month']);
    }

    public function test_attendance_summary_api_returns_session_and_monthwise_data(): void
    {
        $ctx = $this->createStudentWithContext();
        $student = $ctx['student'];

        // Mark attendance in current month
        $date = now()->startOfMonth()->next(Carbon::MONDAY)->toDateString();
        StudentAttendance::create([
            'student_id' => $student->id,
            'date' => $date,
            'attendance_date' => $date,
            'status' => 'present',
            'school_class_id' => $student->class_id,
            'section_id' => $student->section_id,
            'academic_session_id' => $student->academic_session_id,
        ]);

        $response = $this->actingAs($ctx['user'])
            ->getJson('/student/attendance/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'overall_percentage',
                'total_counts' => [
                    'totalWorkingDays',
                    'present',
                    'absent',
                    'late',
                    'leave',
                ],
                'months' => [
                    '*' => [
                        'month',
                        'label',
                        'percentage',
                        'present',
                        'absent',
                        'late',
                        'leave',
                        'totalWorkingDays',
                    ],
                ],
            ]);

        $this->assertEquals(100.0, $response->json('overall_percentage'));
    }

    public function test_guard_enforces_student_id_cannot_be_spoofed_via_query_or_body(): void
    {
        $ctx1 = $this->createStudentWithContext('STD-100');
        $ctx2 = $this->createStudentWithContext('STD-200');

        // Mark attendance for student 2 only
        $date = now()->startOfMonth()->next(Carbon::MONDAY)->toDateString();
        StudentAttendance::create([
            'student_id' => $ctx2['student']->id,
            'date' => $date,
            'attendance_date' => $date,
            'status' => 'present',
            'school_class_id' => $ctx2['student']->class_id,
            'section_id' => $ctx2['student']->section_id,
            'academic_session_id' => $ctx2['student']->academic_session_id,
        ]);

        // Student 1 requests attendance while attempting to pass student_id = Student 2
        $response = $this->actingAs($ctx1['user'])
            ->getJson("/student/attendance?student_id={$ctx2['student']->id}");

        $response->assertOk();
        // Since Student 1 has 0 attendance records, totalWorkingDays must be 0
        $this->assertEquals(0, $response->json('summary.totalWorkingDays'));
    }

    // =========================================================================
    // TASK 2: Leave Request Module
    // =========================================================================

    public function test_student_can_apply_for_leave_with_valid_dates(): void
    {
        Storage::fake('local');
        $ctx = $this->createStudentWithContext();
        $student = $ctx['student'];

        $fromDate = now()->addDays(2)->toDateString();
        $toDate = now()->addDays(4)->toDateString();
        $file = UploadedFile::fake()->create('medical_cert.pdf', 500, 'application/pdf');

        $response = $this->actingAs($ctx['user'])
            ->postJson('/student/leave', [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'reason' => 'Undergoing medical checkup and doctor consultation.',
                'attachment' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('leave.status', 'pending');

        $leave = StudentLeaveRequest::where('student_id', $student->id)->first();
        $this->assertNotNull($leave);
        $this->assertEquals($fromDate, $leave->from_date->toDateString());
        $this->assertEquals($toDate, $leave->to_date->toDateString());
        $this->assertEquals('pending', $leave->status);

        // Verify attachment is stored outside public
        $this->assertNotNull($leave->attachment_path);
        Storage::disk('local')->assertExists($leave->attachment_path);
    }

    public function test_parent_can_apply_for_leave_for_their_child(): void
    {
        $ctx = $this->createStudentWithContext();
        $student = $ctx['student'];

        $parentUser = User::factory()->create([
            'name' => 'Tariq Parent',
            'email' => 'tariq.parent@test.com',
            'role' => 'Parent',
        ]);
        $parentUser->assignRole('Parent');

        // Link in parent_student pivot
        DB::table('parent_student')->insert([
            'parent_id' => $parentUser->id,
            'student_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fromDate = now()->addDays(5)->toDateString();
        $toDate = now()->addDays(6)->toDateString();

        $response = $this->actingAs($parentUser)
            ->withSession(['active_student_id' => $student->id])
            ->postJson('/student/leave', [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'reason' => 'Family travel for sibling graduation ceremony.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('leave.status', 'pending');

        $leave = StudentLeaveRequest::where('student_id', $student->id)->latest('id')->first();
        $this->assertNotNull($leave);
        $this->assertEquals($fromDate, $leave->from_date->toDateString());
        $this->assertEquals($toDate, $leave->to_date->toDateString());
    }

    public function test_leave_validation_rules_and_overlap_prevention(): void
    {
        $ctx = $this->createStudentWithContext();
        $student = $ctx['student'];

        // 1. Past date rejection
        $response = $this->actingAs($ctx['user'])->postJson('/student/leave', [
            'from_date' => now()->subDays(2)->toDateString(),
            'to_date' => now()->toDateString(),
            'reason' => 'Valid reason for past date test.',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['from_date']);

        // 2. to_date < from_date rejection
        $response = $this->actingAs($ctx['user'])->postJson('/student/leave', [
            'from_date' => now()->addDays(5)->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(),
            'reason' => 'Valid reason for date ordering test.',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['to_date']);

        // 3. More than 30 days rejection
        $response = $this->actingAs($ctx['user'])->postJson('/student/leave', [
            'from_date' => now()->addDays(1)->toDateString(),
            'to_date' => now()->addDays(35)->toDateString(),
            'reason' => 'Requesting prolonged absence beyond allowed limit.',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['to_date']);

        // 4. Short reason (< 10 chars) rejection
        $response = $this->actingAs($ctx['user'])->postJson('/student/leave', [
            'from_date' => now()->addDays(1)->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(),
            'reason' => 'Sick',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['reason']);

        // 5. Create a valid pending request
        StudentLeaveRequest::create([
            'student_id' => $student->id,
            'from_date' => now()->addDays(10)->toDateString(),
            'to_date' => now()->addDays(14)->toDateString(),
            'reason' => 'First approved leave request block.',
            'status' => 'pending',
        ]);

        // Attempt overlapping request
        $response = $this->actingAs($ctx['user'])->postJson('/student/leave', [
            'from_date' => now()->addDays(12)->toDateString(),
            'to_date' => now()->addDays(16)->toDateString(),
            'reason' => 'Second leave request overlapping existing block.',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['from_date']);
    }

    public function test_student_can_list_and_cancel_pending_leave(): void
    {
        $ctx = $this->createStudentWithContext();
        $student = $ctx['student'];

        $leave = StudentLeaveRequest::create([
            'student_id' => $student->id,
            'from_date' => now()->addDays(3)->toDateString(),
            'to_date' => now()->addDays(4)->toDateString(),
            'reason' => 'Attending urgent personal family matter.',
            'status' => 'pending',
        ]);

        // 1. List leaves
        $response = $this->actingAs($ctx['user'])
            ->getJson('/student/leave');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $leave->id);

        // 2. Cancel leave
        $cancelResponse = $this->actingAs($ctx['user'])
            ->patchJson("/student/leave/{$leave->id}/cancel");

        $cancelResponse->assertOk()
            ->assertJsonPath('leave.status', 'cancelled');

        $this->assertEquals('cancelled', $leave->fresh()->status);

        // 3. Attempting to cancel already cancelled leave returns 422
        $cancelAgain = $this->actingAs($ctx['user'])
            ->patchJson("/student/leave/{$leave->id}/cancel");

        $cancelAgain->assertStatus(422);
    }

    // =========================================================================
    // TASK 2: Admin / Teacher Approval Flow
    // =========================================================================

    public function test_teacher_can_approve_leave_and_it_auto_marks_attendance_on_working_days(): void
    {
        $ctx = $this->createStudentWithContext();
        $student = $ctx['student'];

        // Create teacher assigned to student's class
        $teacherUser = User::factory()->create([
            'name' => 'Teacher Asad',
            'email' => 'asad.teacher@test.com',
            'role' => 'Teacher',
        ]);
        $teacherUser->assignRole('Teacher');

        $teacher = $this->createTeacher($teacherUser, 'Teacher Asad');

        $subject = \App\Models\Subject::firstOrCreate(['name' => 'General Science']);

        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $student->class_id,
            'section_id' => $student->section_id,
            'subject_id' => $subject->id,
            'session_id' => $student->academic_session_id,
        ]);

        // Pick Monday & Tuesday for leave
        $nextMonday = CarbonImmutable::now()->next(Carbon::MONDAY);
        $nextTuesday = $nextMonday->addDay();

        $leave = StudentLeaveRequest::create([
            'student_id' => $student->id,
            'from_date' => $nextMonday->toDateString(),
            'to_date' => $nextTuesday->toDateString(),
            'reason' => 'Doctor advised two days bed rest for fever recovery.',
            'status' => 'pending',
        ]);

        // Teacher approves leave
        $response = $this->actingAs($teacherUser)
            ->post("/teacher/student-leaves/{$leave->id}/review", [
                'status' => 'approved',
                'review_note' => 'Approved. Take rest and feel better.',
            ]);

        $response->assertRedirect();
        $this->assertEquals('approved', $leave->fresh()->status);
        $this->assertEquals('Approved. Take rest and feel better.', $leave->fresh()->review_note);

        // Verify attendance auto-marked as "leave" for those 2 days
        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $student->id,
            'date' => $nextMonday->toDateString(),
            'status' => 'leave',
        ]);

        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $student->id,
            'date' => $nextTuesday->toDateString(),
            'status' => 'leave',
        ]);

        // Verify notification was created for student user
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $ctx['user']->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_leave_approval_does_not_overwrite_already_present_attendance(): void
    {
        $ctx = $this->createStudentWithContext();
        $student = $ctx['student'];

        $admin = User::factory()->create(['name' => 'School Admin', 'role' => 'Admin']);
        $admin->assignRole('Admin');

        $monday = CarbonImmutable::now()->next(Carbon::MONDAY);

        // Pre-mark student as 'present' on that Monday
        StudentAttendance::create([
            'student_id' => $student->id,
            'date' => $monday->toDateString(),
            'attendance_date' => $monday->toDateString(),
            'status' => 'present',
            'school_class_id' => $student->class_id,
            'section_id' => $student->section_id,
            'academic_session_id' => $student->academic_session_id,
        ]);

        $leave = StudentLeaveRequest::create([
            'student_id' => $student->id,
            'from_date' => $monday->toDateString(),
            'to_date' => $monday->toDateString(),
            'reason' => 'Attended morning exam, applied half day leave.',
            'status' => 'pending',
        ]);

        // Admin approves
        $this->actingAs($admin)
            ->post("/admin/student-leaves/{$leave->id}/review", [
                'status' => 'approved',
            ]);

        // Record on that Monday must still remain 'present' (not overwritten)
        $att = StudentAttendance::where('student_id', $student->id)
            ->where('date', $monday->toDateString())
            ->first();

        $this->assertEquals('present', $att->status);
    }

    public function test_teacher_cannot_review_leave_for_unassigned_class(): void
    {
        $ctx = $this->createStudentWithContext();
        $student = $ctx['student'];

        $otherClass = SchoolClass::create(['name' => 'Grade 8']);
        $teacherUser = User::factory()->create(['name' => 'Other Teacher', 'role' => 'Teacher']);
        $teacherUser->assignRole('Teacher');

        $teacher = $this->createTeacher($teacherUser, 'Other Teacher');

        $subject = \App\Models\Subject::firstOrCreate(['name' => 'General Science']);

        // Assign teacher to Grade 8, but student is in Grade 10
        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $otherClass->id,
            'section_id' => $student->section_id,
            'subject_id' => $subject->id,
            'session_id' => $student->academic_session_id,
        ]);

        $leave = StudentLeaveRequest::create([
            'student_id' => $student->id,
            'from_date' => now()->addDays(1)->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(),
            'reason' => 'Family event attendance.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($teacherUser)
            ->post("/teacher/student-leaves/{$leave->id}/review", [
                'status' => 'approved',
            ]);

        $response->assertStatus(403);
    }

    // =========================================================================
    // TASK 3: Attendance Blade UI
    // =========================================================================

    public function test_attendance_blade_page_renders_with_components(): void
    {
        $ctx = $this->createStudentWithContext();

        $response = $this->actingAs($ctx['user'])
            ->get('/portal/attendance');

        $response->assertOk()
            ->assertSee('My Attendance')
            ->assertSee('Working Days')
            ->assertSee('Attendance Rate')
            ->assertSee('Apply for Leave')
            ->assertSee('My Leave Requests');
    }
}

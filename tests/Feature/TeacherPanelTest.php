<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\TeacherPanelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function demo(): array
    {
        $this->seed(TeacherPanelSeeder::class);
        $assignment = TeacherAssignment::orderBy('id')->firstOrFail();
        $period = \App\Models\Period::create(['name' => 'First period', 'start_time' => '08:00', 'end_time' => '08:40', 'is_break' => false, 'is_active' => true, 'sort_order' => 1]);
        foreach ([today(), today()->addDay()] as $date) {
            \App\Models\TimetableEntry::create(['session_id' => $assignment->session_id, 'class_id' => $assignment->class_id,
                'section_id' => $assignment->section_id, 'subject_id' => $assignment->subject_id, 'teacher_id' => $assignment->teacher_id,
                'period_id' => $period->id, 'day_of_week' => $date->dayOfWeekIso, 'status' => 'published']);
        }
        // These panel tests exercise the class-card layout; timetable layout is tested separately.
        $this->partialMock(\App\Services\TimetableService::class)->shouldReceive('getTodayTeacherPeriods')->andReturn([]);
        return [User::where('email', 'teacher@test.com')->firstOrFail(), TeacherAssignment::orderBy('id')->firstOrFail()];
    }

    public function test_teacher_and_admin_panels_are_separate_and_role_protected(): void
    {
        [$teacher] = $this->demo();
        $this->actingAs($teacher)->get('/teacher/dashboard')->assertOk()->assertSee('Teacher navigation')->assertDontSee('Fee Setup')->assertSee('Demo Teacher');
        foreach (['/dashboard', '/students', '/teachers', '/fees/setup/types', '/attendance/students', '/academic-setup'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->get('/')->assertRedirect(route('teacher.dashboard'));
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin)->get('/teacher/dashboard')->assertForbidden();
        $this->get('/dashboard')->assertOk();
        $this->get('/students')->assertOk();
        $principal = User::factory()->create(['role' => 'Principal']);
        $this->actingAs($principal)->get('/dashboard')->assertOk();
        $this->get('/teacher/classes')->assertForbidden();
    }

    public function test_login_redirects_by_role(): void
    {
        $this->demo();
        $this->post('/attendance/login', ['email' => 'teacher@test.com', 'password' => 'password'])->assertRedirect('/teacher/dashboard');
        $this->post('/attendance/logout');
        User::factory()->create(['email' => 'admin@test.com', 'password' => 'password', 'role' => 'Admin']);
        $this->post('/attendance/login', ['email' => 'admin@test.com', 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_students_roster_and_attendance_both_keep_assigned_section(): void
    {
        [$teacher, $assignment] = $this->demo();
        $section = \App\Models\SchoolSection::create(['name' => 'A']);
        $source = Student::where('class_id', $assignment->class_id)->firstOrFail();
        $student = $source->replicate();
        $student->fill(['admission_no' => 'CLASS-WIDE-TEST', 'first_name' => 'New Class Student', 'section_id' => $section->id])->save();
        $inactive = $source->replicate();
        $inactive->fill(['admission_no' => 'INACTIVE-TEST', 'first_name' => 'Inactive Class Student', 'roll_number' => '99', 'status' => 'Inactive'])->save();
        $this->actingAs($teacher)->get(route('teacher.classes.students', $assignment))->assertOk()
            ->assertDontSee('All sections')->assertDontSee('New Class Student')->assertDontSee('Inactive Class Student')
            ->assertViewHas('students', fn ($rows) => $rows->total() === 3);
        $this->get(route('teacher.attendance.show', $assignment))->assertOk()->assertDontSee('New Class Student');
    }

    public function test_class_card_uses_real_section_and_subject_names_matching_students_link(): void
    {
        [$teacher, $assignment] = $this->demo();
        $this->actingAs($teacher)->get(route('teacher.classes.index'))->assertOk()
            ->assertSee('Section Teacher Demo')->assertSee('Teacher Demo English')->assertDontSee('Section A')
            ->assertSee(route('teacher.classes.students', $assignment), false);
        $this->get(route('teacher.classes.students', $assignment))->assertOk()
            ->assertSee('Section Teacher Demo')->assertSee('Teacher Demo English');
    }

    public function test_teacher_cannot_view_or_mark_unassigned_class(): void
    {
        [$teacher, $assignment] = $this->demo();
        $other = User::factory()->create(['role' => 'Teacher']);
        $otherTeacher = $assignment->teacher->replicate(['employee_id', 'cnic', 'user_id']);
        $otherTeacher->fill(['employee_id' => 'OTHER', 'cnic' => '99999-0000002-1', 'user_id' => $other->id])->save();
        $unassigned = $assignment->replicate();
        $unassigned->fill(['teacher_id' => $otherTeacher->id, 'class_id' => SchoolClass::create(['name' => 'Unassigned'])->id])->save();
        $this->actingAs($teacher);
        foreach (['classes/'.$unassigned->id.'/students', 'classes/'.$unassigned->id.'/quiz', 'classes/'.$unassigned->id.'/progress', 'attendance/'.$unassigned->id, 'attendance/'.$unassigned->id.'/register'] as $url) {
            $this->get('/teacher/'.$url)->assertForbidden();
        }
        $this->post('/teacher/attendance/'.$unassigned->id, ['date' => today()->toDateString(), 'rows' => []])->assertForbidden();
        $this->get('/teacher/classes/'.$assignment->id.'/students')->assertOk()->assertSee('Demo Student')->assertDontSee('Fee Setup');
    }

    public function test_daily_upsert_edits_without_duplicates_and_rejects_invalid_roster_and_future_dates(): void
    {
        [$teacher, $assignment] = $this->demo();
        $students = Student::where('class_id', $assignment->class_id)->where('section_id', $assignment->section_id)->get();
        $data = ['date' => today()->toDateString(), 'rows' => $students->map(fn ($s) => ['student_id' => $s->id, 'status' => 'present', 'remarks' => 'Morning'])->all()];
        $url = '/teacher/attendance/'.$assignment->id;
        $this->actingAs($teacher)->get($url)->assertOk();
        $this->post($url, $data)->assertRedirect();
        $data['rows'][0]['status'] = 'absent';
        $this->post($url, $data)->assertRedirect();
        $this->assertDatabaseCount('student_attendances', 3);
        $this->assertDatabaseHas('student_attendances', ['student_id' => $students[0]->id, 'status' => 'absent', 'marked_by' => $teacher->id]);
        $this->get($url)->assertSee('Already marked');
        $this->get($url.'/register')->assertOk()->assertSee('Monthly Register');
        $data['date'] = today()->addDay()->toDateString();
        $this->post($url, $data)->assertSessionHasErrors('date');
        $this->get($url.'?date='.$data['date'])->assertSessionHasErrors('date');
        $data['date'] = today()->toDateString();
        $data['rows'][0]['student_id'] = Student::where('class_id', '!=', $assignment->class_id)->first()->id;
        $this->post($url, $data)->assertSessionHasErrors('rows');
        $this->assertDatabaseCount('student_attendances', 3);
    }

    public function test_dashboard_and_secondary_pages_have_scoped_empty_states_and_seeder_is_idempotent(): void
    {
        [$teacher, $assignment] = $this->demo();
        $this->seed(TeacherPanelSeeder::class);
        $this->assertDatabaseCount('teacher_assignments', 2);
        $this->assertDatabaseCount('students', 6);
        $this->actingAs($teacher)->get('/teacher/dashboard')->assertOk()->assertSee('Not marked')->assertSee('My classes today')->assertSee('Timetable not configured. Showing your assigned classes.')->assertDontSee('Leave balance')->assertDontSee('Pending quizzes')->assertDontSee('Low attendance')->assertSee('No notices available.');
        foreach (['quizzes', 'progress', 'timetable', 'leaves', 'notices', 'profile', 'attendance', 'classes'] as $path) {
            $this->get('/teacher/'.$path)->assertOk();
        }
        TeacherAssignment::where('teacher_id', $teacher->teacher->id)->delete();
        \Illuminate\Support\Facades\Cache::flush();
        $this->get('/teacher/dashboard')->assertOk()->assertSee('No classes assigned yet.');
    }

    public function test_database_unique_constraint_prevents_duplicate_student_date(): void
    {
        [$teacher, $assignment] = $this->demo();
        $student = Student::where('class_id', $assignment->class_id)->firstOrFail();
        $row = ['student_id' => $student->id, 'date' => today()->toDateString(), 'status' => 'present', 'marked_by' => $teacher->id];
        StudentAttendance::create($row);
        $this->expectException(\Illuminate\Database\QueryException::class);
        StudentAttendance::create($row);
    }

    public function test_dashboard_attendance_updates_and_low_attendance_and_notices_are_real(): void
    {
        [$teacher, $assignment] = $this->demo();
        $students = Student::where('class_id', $assignment->class_id)->get();
        \App\Models\SchoolEvent::create(['title' => 'School meeting', 'description' => 'For all teachers', 'date' => today(), 'type' => 'meeting', 'color' => '#3157f5']);
        $this->actingAs($teacher)->get('/teacher/dashboard')->assertSee('School meeting')->assertViewHas('pendingAttendance', 1);
        $this->post('/teacher/attendance/'.$assignment->id, ['date' => today()->toDateString(), 'rows' => $students->map(fn ($s) => ['student_id' => $s->id, 'status' => 'absent'])->all()])->assertSessionHasNoErrors();
        $this->get('/teacher/dashboard')->assertViewHas('pendingAttendance', 0)->assertViewHas('lowAttendanceCount', 3)->assertSee('Marked');
        $otherUser = User::factory()->create(['role' => 'Teacher']);
        $this->actingAs($otherUser)->get('/teacher/dashboard')->assertViewHas('classCount', 0)->assertViewHas('lowAttendanceCount', 0);
    }

    public function test_quick_mark_json_save_validates_complete_roster_and_returns_fresh_counts(): void
    {
        [$teacher, $assignment] = $this->demo();
        $students = app(\App\Services\TeacherPanelService::class)->roster($assignment)->get();
        $rows = $students->map(fn ($student, $index) => ['student_id' => $student->id,
            'status' => ['present', 'absent', 'late'][$index]])->all();
        $this->actingAs($teacher)->get('/teacher/dashboard')->assertViewHas('pendingAttendance', 1);
        $url = '/teacher/attendance/'.$assignment->id;
        $this->postJson($url, ['date' => today()->toDateString(), 'rows' => array_slice($rows, 1)])
            ->assertUnprocessable()->assertJsonValidationErrors('rows');
        $this->assertDatabaseCount('student_attendances', 0);
        $this->postJson($url, ['date' => today()->toDateString(), 'rows' => $rows])
            ->assertOk()->assertJson(['marked' => true, 'present' => 1, 'total' => 3, 'dashboard' => 0, 'lowAttendanceCount' => 1]);
        $this->get('/teacher/dashboard')->assertViewHas('pendingAttendance', 0)->assertSee('1/3 present');
        $this->postJson($url, ['date' => today()->addDay()->toDateString(), 'rows' => $rows])
            ->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->postJson($url, ['date' => today()->toDateString(), 'rows' => [['student_id' => $students[0]->id, 'status' => 'invalid']]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.0.status');
        $this->actingAs(User::factory()->create(['role' => 'Teacher']))->postJson($url, ['date' => today()->toDateString(), 'rows' => $rows])->assertForbidden();
    }

    public function test_dashboard_deduplicates_subject_assignments_and_hides_unavailable_actions(): void
    {
        [$teacher, $assignment] = $this->demo();
        $extra = $assignment->replicate();
        $extra->subject_id = \App\Models\Subject::create(['name' => 'Mathematics', 'code' => 'MATH-TEST', 'type' => 'theory', 'category' => 'compulsory', 'status' => 'Active'])->id;
        $extra->save();
        $response = $this->actingAs($teacher)->get('/teacher/dashboard')->assertOk()
            ->assertViewHas('registerTotal', 1)->assertViewHas('pendingAttendance', 1)
            ->assertSee('Mathematics')->assertDontSee('New notice')->assertDontSee('Apply leave')->assertDontSee('Add quiz')
            ->assertDontSee('data-day-headline', false);
        $this->assertCount(2, $response['registers']);
        $this->assertSame(1, substr_count($response->getContent(), 'class="td-quick-panel"'));
    }

    public function test_optional_timetable_adapter_switches_to_period_view_and_supports_free_periods(): void
    {
        [$teacher, $assignment] = $this->demo();
        $service = $this->partialMock(\App\Services\TeacherPanelService::class);
        $service->shouldReceive('todayPeriods')->once()->andReturn([
            ['assignment_id' => $assignment->id, 'starts_at' => today()->setTime(8, 0)->toIso8601String(), 'ends_at' => today()->setTime(8, 40)->toIso8601String()],
            ['assignment_id' => null, 'starts_at' => today()->setTime(8, 40)->toIso8601String(), 'ends_at' => today()->setTime(9, 0)->toIso8601String()],
        ]);
        $this->actingAs($teacher)->get('/teacher/dashboard')->assertOk()->assertSee("Today's timetable")
            ->assertDontSee('Timetable not configured')->assertSee('data-day-headline', false)->assertSee('data-period-start', false)->assertSee('Free period');
    }

    public function test_leave_balance_387_was_combined_paid_and_unpaid_allowances(): void
    {
        [$teacher] = $this->demo();
        $types = \App\Models\LeaveType::all();
        $this->assertSame(387, (int) $types->sum('annual_days'));
        $this->assertSame(22, (int) $types->where('is_paid', true)->sum('annual_days'));
        $this->assertSame(365, (int) $types->where('is_paid', false)->sum('annual_days'));
        $this->actingAs($teacher)->get('/teacher/dashboard')->assertDontSee('Leave balance')->assertDontSee('387');
    }
}

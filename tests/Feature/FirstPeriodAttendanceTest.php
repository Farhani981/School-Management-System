<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\TimetableEntry;
use App\Models\User;
use Database\Seeders\TeacherPanelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirstPeriodAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sets_teacher_lecture_order_for_entire_week_and_teacher_cannot_edit(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-07 10:00:00'));
        $this->seed(TeacherPanelSeeder::class);
        $teacher = User::where('email', 'teacher@test.com')->firstOrFail();
        $assignments = TeacherAssignment::where('teacher_id', $teacher->teacher->id)->orderBy('id')->get();
        $first = Period::create(['name' => 'Lecture 1', 'start_time' => '08:00', 'end_time' => '08:40', 'is_active' => true, 'is_break' => false, 'sort_order' => 1]);
        $second = Period::create(['name' => 'Lecture 2', 'start_time' => '08:40', 'end_time' => '09:20', 'is_active' => true, 'is_break' => false, 'sort_order' => 2]);
        $admin = User::factory()->create(['role' => 'Admin']);
        $data = ['teacher_id' => $teacher->teacher->id, 'session_id' => $assignments[0]->session_id,
            'teaching_days' => 7, 'periods' => [$first->id => $assignments[0]->id, $second->id => $assignments[1]->id]];
        $this->actingAs($admin)->get(route('timetable.teacher-routine'))->assertOk();
        $this->post(route('timetable.teacher-routine.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('timetable_entries', 14);
        foreach (range(1, 7) as $day) {
            $this->assertDatabaseHas('timetable_entries', ['teacher_id' => $teacher->teacher->id, 'class_id' => $assignments[0]->class_id, 'period_id' => $first->id, 'day_of_week' => $day, 'status' => 'published']);
            $this->assertDatabaseHas('timetable_entries', ['teacher_id' => $teacher->teacher->id, 'class_id' => $assignments[1]->class_id, 'period_id' => $second->id, 'day_of_week' => $day, 'status' => 'published']);
        }
        $this->actingAs($teacher)->get(route('teacher.timetable.index'))->assertOk()->assertSee('My Daily Teaching Routine')->assertDontSee('My Weekly Teaching Schedule');
        $this->post(route('timetable.teacher-routine.store'), $data)->assertForbidden();
        $this->get(route('timetable.teacher-routine'))->assertForbidden();
        $this->actingAs($admin);
        $data['periods'][$second->id] = 999999;
        $this->post(route('timetable.teacher-routine.store'), $data)->assertSessionHasErrors('periods.'.$second->id);
        $this->assertDatabaseCount('timetable_entries', 14);
    }

    public function test_daily_routine_repeats_first_period_for_seven_days_and_rolls_back_on_conflict(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-07 10:00:00'));
        $this->seed(TeacherPanelSeeder::class);
        $teacher = User::where('email', 'teacher@test.com')->firstOrFail();
        $assignment = TeacherAssignment::where('teacher_id', $teacher->teacher->id)->firstOrFail();
        $period = Period::create(['name' => 'First', 'start_time' => '08:00', 'end_time' => '08:40', 'is_active' => true, 'is_break' => false, 'sort_order' => 1]);
        $admin = User::factory()->create(['role' => 'Admin']);
        $slot = ['session_id' => $assignment->session_id, 'class_id' => $assignment->class_id, 'section_id' => $assignment->section_id,
            'subject_id' => $assignment->subject_id, 'teacher_id' => $assignment->teacher_id, 'period_id' => $period->id, 'day_of_week' => 1];
        app(\App\Services\TimetableService::class)->saveEntry($slot, $admin->id);
        $routine = collect($slot)->only(['session_id', 'class_id', 'section_id'])->all() + ['source_day' => 1, 'teaching_days' => 7];
        $this->actingAs($admin)->post(route('timetable.routine.repeat'), $routine)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('timetable_entries', 7);
        app(\App\Services\TimetableService::class)->setPublishStatus($assignment->session_id, $assignment->class_id, $assignment->section_id, true);
        foreach (range(5, 11) as $day) {
            $this->assertTrue(app(\App\Services\FirstPeriodAttendance::class)->allows($teacher, $assignment->class_id, $assignment->section_id, $assignment->session_id, sprintf('2026-10-%02d', $day)));
        }
        $this->actingAs($teacher)->post(route('timetable.routine.repeat'), $routine)->assertForbidden();
        TimetableEntry::where('day_of_week', '!=', 1)->delete();
        $other = TeacherAssignment::where('id', '!=', $assignment->id)->firstOrFail();
        TimetableEntry::create(array_merge($slot, ['class_id' => $other->class_id, 'day_of_week' => 4, 'status' => 'draft']));
        $this->actingAs($admin)->post(route('timetable.routine.repeat'), $routine)->assertSessionHasErrors('teacher_id');
        $this->assertDatabaseCount('timetable_entries', 2);
    }

    public function test_only_published_first_period_class_can_be_marked_on_the_selected_day(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-07 10:00:00'));
        $this->seed(TeacherPanelSeeder::class);
        $user = User::where('email', 'teacher@test.com')->firstOrFail();
        $assignments = TeacherAssignment::where('teacher_id', $user->teacher->id)->orderBy('id')->get();
        $first = Period::create(['name' => 'First', 'start_time' => '08:00', 'end_time' => '08:40', 'is_active' => true, 'is_break' => false, 'sort_order' => 1]);
        $second = Period::create(['name' => 'Second', 'start_time' => '08:40', 'end_time' => '09:20', 'is_active' => true, 'is_break' => false, 'sort_order' => 2]);
        $this->actingAs($user);
        foreach ($assignments as $i => $assignment) {
            TimetableEntry::create(['session_id' => $assignment->session_id, 'class_id' => $assignment->class_id,
                'section_id' => $assignment->section_id, 'subject_id' => $assignment->subject_id,
                'teacher_id' => $user->teacher->id, 'period_id' => $i === 0 ? $first->id : $second->id,
                'day_of_week' => 3, 'status' => 'published', 'created_by' => $user->id]);
        }
        $this->get(route('teacher.attendance.index'))->assertOk()->assertViewHas('cards', fn ($cards) => count($cards) === 1 && $cards[0]['assignment']->id === $assignments[0]->id);
        Period::create(['name' => 'Morning Break', 'start_time' => '09:20', 'end_time' => '09:40', 'is_active' => true, 'is_break' => true, 'sort_order' => 3]);
        $dashboard = $this->get(route('teacher.dashboard'))->assertOk()->assertSee('First-period attendance')
            ->assertSee('Teaching period')->assertSee('Morning Break')->assertSee('data-period-label="Break"', false);
        $this->assertSame(1, substr_count($dashboard->getContent(), 'class="td-quick-panel"'));
        $this->get(route('teacher.attendance.show', $assignments[0]))->assertOk();
        $this->get(route('teacher.attendance.show', $assignments[1]))->assertForbidden();
        $this->get(route('teacher.attendance.show', ['assignment' => $assignments[0], 'date' => '2026-10-06']))->assertForbidden();
        foreach ($assignments as $i => $assignment) {
            $students = Student::where('class_id', $assignment->class_id)->where('section_id', $assignment->section_id)->get();
            $data = ['date' => '2026-10-07', 'rows' => $students->map(fn ($student) => ['student_id' => $student->id, 'status' => 'present'])->all()];
            $response = $this->postJson(route('teacher.attendance.store', $assignment), $data);
            $i === 0 ? $response->assertOk() : $response->assertForbidden();
        }
        $this->assertDatabaseCount('student_attendances', 3);
        $entry = TimetableEntry::where('period_id', $first->id)->firstOrFail();
        $entry->update(['status' => 'draft']);
        $this->get(route('teacher.attendance.show', $assignments[0]))->assertForbidden();
        $entry->update(['status' => 'published']);
        $entry->overrides()->create(['date' => '2026-10-07', 'type' => 'cancelled', 'created_by' => $user->id]);
        $this->get(route('teacher.attendance.show', $assignments[0]))->assertForbidden();
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->assertTrue($admin->can('mark', [\App\Models\SchoolClass::findOrFail($assignments[1]->class_id), $assignments[1]->section_id, $assignments[1]->session_id, '2026-10-07']));
    }
}

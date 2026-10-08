<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_break_can_be_inserted_at_occupied_order_and_moved_without_losing_periods(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
        $fourth = \App\Models\Period::create(['name' => 'Period 4', 'start_time' => '10:00', 'end_time' => '10:45', 'sort_order' => 4, 'is_active' => true, 'is_break' => false]);
        $fifth = \App\Models\Period::create(['name' => 'Period 5', 'start_time' => '11:00', 'end_time' => '11:45', 'sort_order' => 5, 'is_active' => true, 'is_break' => false]);
        $sixth = \App\Models\Period::create(['name' => 'Period 6', 'start_time' => '11:45', 'end_time' => '12:30', 'sort_order' => 6, 'is_active' => true, 'is_break' => false]);
        $data = ['name' => 'Morning Break', 'start_time' => '10:45', 'end_time' => '11:00', 'sort_order' => 5, 'is_break' => 1, 'is_active' => 1, 'creating_period' => 1];
        $this->post(route('timetable.periods.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $break = \App\Models\Period::where('name', 'Morning Break')->firstOrFail();
        $this->assertSame(5, $break->sort_order);
        $this->assertSame(6, $fifth->fresh()->sort_order);
        $this->assertSame(7, $sixth->fresh()->sort_order);
        $this->assertSame(4, $fourth->fresh()->sort_order);
        $data['sort_order'] = 7;
        $this->put(route('timetable.periods.update', $break), $data)->assertSessionHasNoErrors();
        $this->assertSame(7, $break->fresh()->sort_order);
        $this->assertSame(5, $fifth->fresh()->sort_order);
        $data['sort_order'] = 5;
        $this->put(route('timetable.periods.update', $break), $data)->assertSessionHasNoErrors();
        $this->assertSame(5, $break->fresh()->sort_order);
        $this->assertSame(7, $sixth->fresh()->sort_order);
        $before = \App\Models\Period::orderBy('id')->pluck('sort_order')->all();
        $data['name'] = 'Overlapping Break';
        $data['start_time'] = '10:30';
        $this->post(route('timetable.periods.store'), $data)->assertSessionHasErrors('start_time');
        $this->get(route('timetable.periods.index'))->assertOk()->assertSee('period-create-retry', false);
        $this->assertSame($before, \App\Models\Period::orderBy('id')->pluck('sort_order')->all());
        $this->assertDatabaseCount('periods', 4);
    }

    public function test_period_edit_saves_timings_and_refreshes_dashboard_cache_and_keeps_rejected_values(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin);
        $period = \App\Models\Period::create(['name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:45', 'sort_order' => 1, 'is_break' => false, 'is_active' => true]);
        \App\Models\Period::create(['name' => 'Period 2', 'start_time' => '08:45', 'end_time' => '09:30', 'sort_order' => 2, 'is_break' => false, 'is_active' => true]);
        $previousKey = \App\Services\TeacherPanelService::cacheKey($admin);
        $data = ['name' => 'First Lecture', 'start_time' => '07:55', 'end_time' => '08:40', 'sort_order' => 1, 'is_active' => 1, 'editing_period_id' => $period->id];
        $this->put(route('timetable.periods.update', $period), $data)->assertSessionHasNoErrors()->assertRedirect(route('timetable.periods.index'));
        $this->assertSame('First Lecture', $period->fresh()->name);
        $this->assertSame('07:55:00', \Carbon\Carbon::parse($period->fresh()->start_time)->format('H:i:s'));
        $this->assertNotSame($previousKey, \App\Services\TeacherPanelService::cacheKey($admin));
        $data['end_time'] = '09:00';
        $this->from(route('timetable.periods.index'))->put(route('timetable.periods.update', $period), $data)->assertSessionHasErrors('start_time');
        $this->get(route('timetable.periods.index'))->assertOk()->assertSee('period-edit-retry', false)->assertSee('This time slot overlaps');
        $this->assertSame('08:40:00', \Carbon\Carbon::parse($period->fresh()->end_time)->format('H:i:s'));
    }

    public function test_admin_can_open_timetable_overview_and_builder_with_shared_sections(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
        $session = AcademicSession::create(['name' => '2026', 'is_current' => true]);
        SchoolClass::create(['name' => 'Class 1']);
        $class = SchoolClass::create(['name' => 'Class 2']);
        SchoolSection::create(['name' => 'A']);
        $section = SchoolSection::create(['name' => 'B']);

        $this->get(route('timetable.index'))
            ->assertOk()
            ->assertViewHas('totalSections', 4);

        $this->get(route('timetable.builder', [
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
        ]))->assertOk()
            ->assertViewHas('selectedClass', fn ($selected) => $selected->is($class))
            ->assertViewHas('selectedSection', fn ($selected) => $selected->is($section))
            ->assertViewHas('viewMode', 'daily')->assertViewHas('days', fn ($days) => $days->keys()->all() === [1]);
        $this->get(route('timetable.builder', ['session_id' => $session->id, 'class_id' => $class->id, 'section_id' => $section->id, 'view' => 'weekly']))
            ->assertOk()->assertViewHas('days', fn ($days) => $days->count() === 7);
    }

    public function test_admin_can_open_timetable_pages_before_setup(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin']));

        $this->get(route('timetable.index'))->assertOk();
        $this->get(route('timetable.builder'))->assertOk();
    }

    public function test_teacher_builder_bookmark_redirects_without_granting_edit_access(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Teacher']));
        $this->get('/timetable/builder?session_id=1&class_id=4&section_id=1')
            ->assertRedirect(route('teacher.timetable.index'));
        $this->post(route('timetable.entries.store'), [])->assertForbidden();
        $this->post(route('timetable.publish'), [])->assertForbidden();
    }

    public function test_builder_keeps_principal_access_and_rejects_other_roles(): void
    {
        $this->get(route('timetable.builder'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'Principal']))
            ->get(route('timetable.builder'))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'Other']))
            ->get(route('timetable.builder'))->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Activity;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\User;
use App\Services\DashboardStatsService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\CreatesFeeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
        $this->travelTo(Carbon::parse('2026-10-02 10:00:00', 'Asia/Karachi'));
        Cache::flush();
    }

    private function student(array $data = []): Student
    {
        $session = AcademicSession::firstOrCreate(['name' => '2026-27'], ['is_current' => true]);

        return Student::create(array_replace([
            'admission_no' => 'ADM-2026-'.(Student::withTrashed()->count() + 1), 'first_name' => 'Ali', 'last_name' => 'Khan',
            'father_name' => 'Ahmed', 'date_of_birth' => '2016-01-01', 'gender' => 'Male', 'phone' => '03001234567',
            'address' => 'Lahore', 'admission_date' => '2026-10-01', 'roll_number' => '1', 'status' => 'Active', 'academic_session_id' => $session->id,
        ], $data));
    }

    public function test_dashboard_with_zero_data_and_missing_finance_modules(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertSee('Boys 0 / Girls 0')->assertSee('Not added yet')->assertSee('Rs. 0')->assertSee('No fee data yet')->assertSee('Attendance not marked yet')->assertSee('No upcoming events')->assertSee('No recent activity')->assertSee('Fri, 02 Oct 2026')->assertDontSee('Sample feed')->assertDontSee('demonstration data');
        $service = app(DashboardStatsService::class);
        $this->assertSame(0, $service->students()['total']);
        $this->assertTrue($service->fees()['empty']);
        $this->assertEquals(0, $service->fees()['percentage']);
    }

    public function test_current_active_students_and_immediate_cache_invalidation(): void
    {
        $service = app(DashboardStatsService::class);
        $this->assertSame(0, $service->students()['total']);
        $student = $this->student();
        $revision = Cache::get('dashboard:revision');
        $this->assertSame(1, $service->students()['total']);
        $this->student(['gender' => 'Female', 'roll_number' => '2']);
        $other = AcademicSession::create(['name' => '2025-26', 'is_current' => false]);
        $this->student(['academic_session_id' => $other->id]);
        $this->student(['status' => 'Inactive']);
        $this->get('/')->assertOk()->assertSee('Boys 1 / Girls 1');
        $student->update(['status' => 'Inactive']);
        $this->assertDatabaseHas('activities', ['description' => 'Student updated: Ali Khan']);
        $this->assertNotSame($revision, Cache::get('dashboard:revision'));
        $this->assertSame(1, $service->students()['total']);
        $student->delete();
        $student->restore();
        $student->update(['status' => 'Active']);
        $this->assertSame(2, $service->students()['total']);
        $this->assertDatabaseHas('activities', ['type' => 'student', 'description' => 'Student deleted: Ali Khan']);
    }

    public function test_attendance_counts_current_students_and_refreshes_after_marking(): void
    {
        $service = app(DashboardStatsService::class);
        $student = $this->student();
        $this->student(['gender' => 'Female', 'roll_number' => '2']);
        $this->assertTrue($service->attendance()['empty']);
        $student->attendances()->create(['date' => '2026-10-02', 'status' => 'present', 'academic_session_id' => $student->academic_session_id]);
        $stats = $service->attendance();
        $this->assertSame(1, $stats['counts']['present']);
        $this->assertSame(50.0, $stats['percentage']);
        $this->get('/')->assertOk()->assertSee('50.0')->assertDontSee('Attendance not marked yet');
        $student->attendances()->first()->update(['status' => 'Leave']);
        $stats = $service->attendance();
        $this->assertSame(1, $stats['counts']['leave']);
        $this->assertCount(4, $stats['rows']);
        $this->get('/')->assertOk()->assertSee('View Full Attendance')->assertSee('Leave');
    }

    public function test_event_crud_calendar_ranges_and_access(): void
    {
        \Illuminate\Support\Facades\Auth::logout();
        $this->get(route('events.index'))->assertRedirect(route('login'));
        $user = User::factory()->create(['name' => 'Farhan Admin', 'role' => 'Admin']);
        $this->actingAs($user)->get(route('events.create'))->assertOk();
        $data = ['title' => 'Science Exam', 'description' => 'Classes 6 to 10', 'date' => '2026-10-10', 'end_date' => '2026-10-12', 'type' => 'exam', 'color' => 'blue'];
        $this->post(route('events.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('events.index'));
        $event = SchoolEvent::firstOrFail();
        $this->get('/')->assertOk()->assertSee('Science Exam')->assertSee('Farhan Admin')->assertSee('Admin')->assertDontSee('sample admin workspace');
        $this->getJson(route('dashboard.calendar', ['month' => '2026-10']))->assertOk()->assertJsonPath('events.0.title', 'Science Exam')->assertJsonPath('events.0.end_date', '2026-10-12');
        $this->getJson(route('dashboard.calendar', ['month' => 'bad']))->assertUnprocessable();
        $this->get(route('events.edit', $event))->assertOk();
        $this->put(route('events.update', $event), array_replace($data, ['title' => 'Updated Exam', 'date' => '2026-11-10', 'end_date' => null]))->assertSessionHasNoErrors();
        $this->getJson(route('dashboard.calendar', ['month' => '2026-11']))->assertJsonPath('events.0.title', 'Updated Exam');
        $this->delete(route('events.destroy', $event))->assertRedirect(route('events.index'));
        $this->getJson(route('dashboard.calendar', ['month' => '2026-11']))->assertJsonPath('empty', true);
        $this->actingAs(User::factory()->create(['role' => 'Teacher']))->get(route('events.index'))->assertForbidden();
    }

    public function test_finance_aggregates_supported_optional_tables(): void
    {
        $student = $this->feeStudent();
        $this->feeType($student, 'Tuition', 'monthly', 10000);
        $billing = app(\App\Services\FeeBillingService::class);
        $voucher = $billing->generate($student, 10, 2026, auth()->user());
        $billing->collect($voucher, ['amount' => 6000, 'payment_date' => '2026-10-02', 'method' => 'Cash', 'submission_token' => (string) \Illuminate\Support\Str::uuid()], auth()->user());
        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->decimal('amount', 10, 2);
        });
        DB::table('expenses')->insert(['date' => '2026-10-02', 'amount' => 2000]);
        DashboardStatsService::invalidate();
        $fees = app(DashboardStatsService::class)->fees();
        $this->assertEquals(10000, $fees['billed']);
        $this->assertEquals(4000, $fees['pending']);
        $this->assertEquals(6000, $fees['collected']);
        $this->assertEquals(2000, $fees['expenses']);
        $this->assertEquals(60, $fees['percentage']);
        $this->get('/')->assertOk()->assertSee('Rs. 4,000')->assertDontSee('No fee data yet')
            ->assertSee('Total Fees')->assertSee('Monthly collections')->assertSee('Quick Actions')
            ->assertViewHas('feeTrend', fn ($trend) => $trend->count() === 6 && $trend->last()['amount'] == 6000);
    }

    public function test_current_session_switch_and_activity_limit(): void
    {
        $this->student();
        $other = AcademicSession::create(['name' => '2027-28']);
        $this->actingAs(User::factory()->create(['role' => 'Admin']))->post(route('academic-setup.current'), ['academic_session_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame(1, AcademicSession::where('is_current', true)->count());
        $this->assertSame(0, app(DashboardStatsService::class)->students()['total']);
        for ($i = 0; $i < 8; $i++) {
            Activity::create(['description' => 'Test activity '.$i, 'type' => 'student', 'created_at' => now()->subMinutes(8 - $i)]);
        }
        $this->assertCount(5, app(DashboardStatsService::class)->activities()['items']);
        $this->get(route('academic-setup.index'))->assertOk()->assertSee('Set Current Session');
    }

    public function test_widgets_tolerate_missing_module_tables(): void
    {
        $tables = ['students', 'teachers', 'school_classes', 'school_sections', 'student_attendances', 'academic_sessions', 'events', 'activities', 'holidays'];
        try {
            foreach ($tables as $table) {
                Schema::rename($table, $table.'_unavailable');
            }
            DashboardStatsService::invalidate();
            $this->get('/')->assertOk()->assertSee('Boys 0 / Girls 0')->assertSee('No upcoming events')->assertSee('No recent activity');
        } finally {
            foreach (array_reverse($tables) as $table) {
                if (Schema::hasTable($table.'_unavailable')) {
                    Schema::rename($table.'_unavailable', $table);
                }
            }
        }
    }

    public function test_widget_cache_expires_after_five_minutes(): void
    {
        $student = $this->student();
        $stats = app(DashboardStatsService::class);
        $this->assertSame(1, $stats->students()['total']);
        DB::table('students')->where('id', $student->id)->update(['status' => 'Inactive']);
        $this->assertSame(1, $stats->students()['total']);
        $this->travel(6)->minutes();
        $this->assertSame(0, $stats->students()['total']);
    }

    public function test_bulk_attendance_invalidates_dashboard_cache(): void
    {
        $class = SchoolClass::create(['name' => 'Class 1']);
        $section = SchoolSection::create(['name' => 'A']);
        $student = $this->student(['class_id' => $class->id, 'section_id' => $section->id]);
        $stats = app(DashboardStatsService::class);
        $this->assertTrue($stats->attendance()['empty']);
        $this->actingAs(User::factory()->create(['role' => 'Admin']))->post(route('attendance.students.store'), [
            'date' => '2026-10-02', 'school_class_id' => $class->id, 'section_id' => $section->id, 'academic_session_id' => $student->academic_session_id,
            'rows' => [['student_id' => $student->id, 'status' => 'absent']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $stats->attendance()['counts']['absent']);
        $this->assertFalse($stats->attendance()['empty']);
    }
}

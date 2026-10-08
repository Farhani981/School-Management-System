<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SchoolReportRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_report_module_is_removed_from_admin_portal(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin']));

        $this->assertFalse(Route::has('reports.index'));
        $this->assertFalse(Route::has('reports.show'));
        $this->get('/reports')->assertNotFound();
        $this->get('/reports/students')->assertNotFound();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('All Reports')
            ->assertDontSee('href="'.url('/reports').'"', false);

        $this->assertTrue(Route::has('attendance.reports.index'));
        $this->assertTrue(Route::has('fees.reports.index'));
    }
}

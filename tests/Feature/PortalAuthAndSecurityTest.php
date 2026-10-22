<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalAuthAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Parent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    }

    private function createStudent(array $attributes = []): Student
    {
        return Student::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'Student',
            'father_name' => 'Test Father',
            'date_of_birth' => '2010-01-01',
            'gender' => 'Male',
            'phone' => '03001234567',
            'address' => 'Test Address',
            'admission_date' => now()->toDateString(),
            'admission_no' => 'ADM-' . uniqid(),
            'roll_number' => 'ROLL-' . rand(100, 999),
            'status' => 'Active',
        ], $attributes));
    }

    public function test_portal_login_page_renders_successfully()
    {
        $response = $this->get(route('portal.login'));
        $response->assertStatus(200);
        $response->assertSee('Student Login');
        $response->assertSee('Parent Login');
    }

    public function test_student_can_login_with_admission_no()
    {
        $user = User::factory()->create([
            'email' => 'student.portal.test@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'Student',
        ]);
        $user->assignRole('Student');

        $student = $this->createStudent([
            'user_id' => $user->id,
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'admission_no' => 'ADM-TEST-999',
            'roll_number' => 'ROLL-999',
            'email' => $user->email,
        ]);

        $response = $this->post(route('portal.login.submit'), [
            'type' => 'student',
            'identifier' => 'ADM-TEST-999',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('portal.home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_parent_can_login_with_phone_and_switch_children()
    {
        $parentUser = User::factory()->create([
            'name' => 'Parent Tariq',
            'email' => 'parent.test@example.com',
            'phone' => '03009998877',
            'password' => Hash::make('parentpass123'),
            'role' => 'Parent',
        ]);
        $parentUser->assignRole('Parent');

        $student1 = $this->createStudent([
            'first_name' => 'Kid One',
            'last_name' => 'Tariq',
            'admission_no' => 'ADM-KID-1',
            'father_name' => 'Parent Tariq',
        ]);

        $student2 = $this->createStudent([
            'first_name' => 'Kid Two',
            'last_name' => 'Tariq',
            'admission_no' => 'ADM-KID-2',
            'father_name' => 'Parent Tariq',
        ]);

        // Link both children to parent in parent_student pivot
        $parentUser->children()->attach([$student1->id, $student2->id]);

        $loginResponse = $this->post(route('portal.login.submit'), [
            'type' => 'parent',
            'identifier' => '03009998877',
            'password' => 'parentpass123',
        ]);

        $loginResponse->assertRedirect(route('portal.home'));
        $this->assertAuthenticatedAs($parentUser);

        // Switch active child to student 2
        $switchResponse = $this->actingAs($parentUser)->post(route('portal.switch-child'), [
            'student_id' => $student2->id,
        ]);

        $switchResponse->assertSessionHas('active_student_id', $student2->id);

        // Try unauthorized child switch to an unlinked student
        $unlinkedStudent = $this->createStudent([
            'first_name' => 'Stranger',
            'last_name' => 'Kid',
            'admission_no' => 'ADM-STRANGER',
        ]);

        $forbiddenResponse = $this->actingAs($parentUser)->post(route('portal.switch-child'), [
            'student_id' => $unlinkedStudent->id,
        ]);

        $forbiddenResponse->assertStatus(403);
    }

    public function test_portal_routes_reject_spoofed_student_id_in_query_or_body()
    {
        $user = User::factory()->create(['role' => 'Student']);
        $user->assignRole('Student');

        $myStudent = $this->createStudent([
            'user_id' => $user->id,
            'first_name' => 'Legit',
            'last_name' => 'Student',
            'admission_no' => 'ADM-LEGIT',
        ]);

        $victimStudent = $this->createStudent([
            'first_name' => 'Victim',
            'last_name' => 'Student',
            'admission_no' => 'ADM-VICTIM',
        ]);

        // Attempt to pass ?student_id=victim to portal home or profile
        $response = $this->actingAs($user)->get(route('portal.home', ['student_id' => $victimStudent->id]));
        $response->assertStatus(200);
        $response->assertSee('ADM-LEGIT');
        $response->assertDontSee('ADM-VICTIM');
    }

    public function test_all_placeholder_pages_render_with_empty_state()
    {
        $user = User::factory()->create(['role' => 'Student']);
        $user->assignRole('Student');

        $student = $this->createStudent([
            'user_id' => $user->id,
            'first_name' => 'Sara',
            'last_name' => 'Ahmed',
            'admission_no' => 'ADM-SARA',
        ]);

        // portal.attendance is now fully implemented
        $attResp = $this->actingAs($user)->get(route('portal.attendance'));
        $attResp->assertStatus(200);
        $attResp->assertSee('My Attendance');

        // portal.fees is now fully implemented
        $feesResp = $this->actingAs($user)->get(route('portal.fees'));
        $feesResp->assertStatus(200);
        $feesResp->assertSee('Fees &amp; Billing', false);

        $placeholderRoutes = [
            'portal.performance',
            'portal.quizzes',
            'portal.results',
            'portal.study-material',
            'portal.timetable',
            'portal.library',
        ];

        foreach ($placeholderRoutes as $route) {
            $resp = $this->actingAs($user)->get(route($route));
            $resp->assertStatus(200);
            $resp->assertSee('Coming Soon');
        }

        $profileResp = $this->actingAs($user)->get(route('portal.profile'));
        $profileResp->assertStatus(200);
        $profileResp->assertSee('Profile Information');
        $profileResp->assertSee('Sara Ahmed');
    }
}

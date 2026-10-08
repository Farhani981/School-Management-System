<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\TeacherPanelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDetailsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setupTeacherAndStudents(): array
    {
        $this->seed(TeacherPanelSeeder::class);
        $teacherUser = User::where('email', 'teacher@test.com')->firstOrFail();
        $assignment = TeacherAssignment::firstOrFail();

        // Student in teacher's assigned class
        $assignedStudent = Student::create([
            'admission_no' => 'ADM-2026-0001',
            'first_name' => 'Seth',
            'last_name' => 'Hallam',
            'father_name' => 'Robert Fox',
            'mother_name' => 'Brooklyn Simmons',
            'date_of_birth' => '2006-11-10',
            'gender' => 'Male',
            'phone' => '03001234567',
            'address' => '8502 Preston Rd, Maine',
            'admission_date' => '2026-01-10',
            'class_id' => $assignment->class_id,
            'section_id' => $assignment->section_id,
            'academic_session_id' => $assignment->session_id,
            'roll_number' => '10',
            'status' => 'Active',
            'blood_group' => 'O+',
            'previous_school' => 'Stuyvesant High School',
            'last_class' => 'Grade 9',
            'leaving_reason' => 'Relocation',
            'allergies' => 'None',
            'height' => '5.8 ft',
            'weight' => '65 kg',
            'remarks' => 'Consistent student with excellent attendance record.',
        ]);

        // Another class not assigned to this teacher
        $otherClass = SchoolClass::create(['name' => 'Grade 11 Unassigned']);
        $otherSection = SchoolSection::create(['name' => 'Z']);

        $unassignedStudent = Student::create([
            'admission_no' => 'ADM-2026-0099',
            'first_name' => 'Zara',
            'last_name' => 'Khan',
            'father_name' => 'Tariq Khan',
            'date_of_birth' => '2007-05-15',
            'gender' => 'Female',
            'phone' => '03009876543',
            'address' => 'House 12, Street 4, Lahore',
            'admission_date' => '2026-01-15',
            'class_id' => $otherClass->id,
            'section_id' => $otherSection->id,
            'academic_session_id' => $assignment->session_id,
            'roll_number' => '99',
            'status' => 'Active',
        ]);

        return [$teacherUser, $assignedStudent, $unassignedStudent];
    }

    public function test_teacher_cannot_open_student_outside_his_classes(): void
    {
        [$teacherUser, $assignedStudent, $unassignedStudent] = $this->setupTeacherAndStudents();

        $this->actingAs($teacherUser);

        // Teacher can open student in his assigned class
        $this->get(route('students.show', $assignedStudent))
            ->assertOk()
            ->assertSee('Seth Hallam')
            ->assertSee('ADM-2026-0001');

        // Teacher CANNOT open student outside his assigned classes -> 403 Forbidden
        $this->get(route('students.show', $unassignedStudent))
            ->assertForbidden();
    }

    public function test_teacher_sees_no_fees_tab_or_documents(): void
    {
        [$teacherUser, $assignedStudent] = $this->setupTeacherAndStudents();

        // Create a document on the assigned student
        $assignedStudent->documents()->create([
            'name' => 'BirthCertificate.pdf',
            'path' => 'students/documents/birth.pdf',
        ]);

        $this->actingAs($teacherUser);

        $response = $this->get(route('students.show', $assignedStudent))
            ->assertOk();

        // Teacher sees basic student details
        $response->assertSee('Seth Hallam');
        $response->assertSee('Parent / Guardian Detail');

        // Teacher MUST NOT see Fees tab button or fees content
        $response->assertDontSee('data-tab="fees"', false);
        $response->assertDontSee('Fees Records &amp; Vouchers', false);

        // Teacher MUST NOT see documents list or document upload
        $response->assertDontSee('BirthCertificate.pdf');
        $response->assertDontSee('Upload Document');
    }

    public function test_admin_and_principal_have_full_access(): void
    {
        [, $assignedStudent, $unassignedStudent] = $this->setupTeacherAndStudents();

        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin);

        // Admin can open any student
        $this->get(route('students.show', $assignedStudent))
            ->assertOk()
            ->assertSee('Seth Hallam')
            ->assertSee('Personal Info')
            ->assertSee('Create Login');

        $this->get(route('students.show', $unassignedStudent))
            ->assertOk()
            ->assertSee('Zara Khan');

        // Principal can open any student
        $principal = User::factory()->create(['role' => 'Principal']);
        $this->actingAs($principal);

        $this->get(route('students.show', $assignedStudent))->assertOk();
        $this->get(route('students.show', $unassignedStudent))->assertOk();
    }

    public function test_student_status_can_be_changed_with_reason_and_history(): void
    {
        [, $assignedStudent] = $this->setupTeacherAndStudents();

        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin);

        $this->post(route('students.status', $assignedStudent), [
            'status' => 'Left',
            'reason' => 'Family relocated to another city.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $assignedStudent->refresh();
        $this->assertSame('Left', $assignedStudent->status);

        // Verify history was recorded
        $this->assertDatabaseHas('student_histories', [
            'student_id' => $assignedStudent->id,
            'event' => 'Status changed to Left',
        ]);
    }

    public function test_portal_login_can_be_created_for_student(): void
    {
        [, $assignedStudent] = $this->setupTeacherAndStudents();

        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin);

        $this->post(route('students.portal-login', $assignedStudent), [
            'email' => 'seth.hallam@student.school.edu',
            'password' => 'Password123!',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $assignedStudent->refresh();
        $this->assertNotNull($assignedStudent->user_id);
        $this->assertSame('seth.hallam@student.school.edu', $assignedStudent->email);

        // Now viewing the page should show "Login Details" button
        $this->get(route('students.show', $assignedStudent))
            ->assertOk()
            ->assertSee('Login Details')
            ->assertDontSee('Create Login');
    }
}

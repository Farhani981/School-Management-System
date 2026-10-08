<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\LibraryMember;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LibraryMemberTest extends TestCase
{
    use RefreshDatabase;

    private function student(array $overrides = []): Student
    {
        return Student::create(array_merge(['admission_no' => 'ADM-'.(Student::withTrashed()->count() + 1), 'first_name' => 'Ali', 'last_name' => 'Hassan', 'father_name' => 'Ahmed',
            'date_of_birth' => '2015-01-10', 'gender' => 'Male', 'phone' => '03001234567', 'address' => 'School Road', 'admission_date' => '2026-09-01',
            'class_id' => SchoolClass::firstOrCreate(['name' => 'Class 1'])->id, 'section_id' => SchoolSection::firstOrCreate(['name' => 'A'])->id,
            'roll_number' => (string) (Student::withTrashed()->count() + 1), 'status' => 'Active'], $overrides));
    }

    private function teacher(array $overrides = []): Teacher
    {
        return Teacher::create(array_merge(['employee_id' => 'TCH-1', 'name' => 'Ayesha Khan', 'cnic' => '12345-1234567-1', 'gender' => 'Female', 'phone' => '03007654321',
            'department_id' => Department::firstOrCreate(['name' => 'Science'])->id, 'designation_id' => Designation::firstOrCreate(['name' => 'Lecturer'])->id,
            'joining_date' => '2026-01-01', 'status' => 'Active'], $overrides));
    }

    private function data(Student|Teacher $person): array
    {
        $student = $person instanceof Student;

        return ['member_type' => $student ? 'Student' : 'Teacher', $student ? 'student_id' : 'teacher_id' => $person->id,
            'registration_date' => '2026-10-07', 'borrowing_limit' => $student ? 3 : 5, 'status' => 'Active'];
    }

    public function test_admin_registers_existing_students_and_teachers_and_details_live_in_actions(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin);
        $student = $this->student();
        $teacher = $this->teacher();
        $this->get(route('library.members.index'))->assertOk()->assertSee('No members found')->assertSee('Add Member');
        $this->get(route('library.members.create'))->assertOk()->assertSee('Ali Hassan')->assertSee('Ayesha Khan');
        foreach ([$student, $teacher] as $person) {
            $this->post(route('library.members.store'), $this->data($person))->assertSessionHasNoErrors();
        }
        $members = LibraryMember::orderBy('id')->get();
        $this->assertMatchesRegularExpression('/^STU-LIB-\d+$/', $members[0]->member_id);
        $this->assertMatchesRegularExpression('/^TCH-LIB-\d+$/', $members[1]->member_id);
        $this->assertNull($members[0]->teacher_id);
        $this->assertNull($members[1]->student_id);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('teachers', 1);
        $this->get(route('library.members.index'))->assertOk()->assertSee('Member Details')->assertSee(route('library.members.show', $members[0]), false)
            ->assertSee('library-row-menu', false)->assertDontSee('>Members Details</button>', false);
        $this->get(route('library.members.show', $members[0]))->assertOk()->assertSee('Ali Hassan')->assertSee('Class 1 / A')->assertSee('3 books');
        $this->get(route('library.members.show', $members[1]))->assertOk()->assertSee('Ayesha Khan')->assertSee('Science')->assertSee('5 books');
        $this->assertDatabaseHas('activities', ['user_id' => $admin->id, 'description' => 'Library member created: '.$members[0]->member_id]);
    }

    public function test_duplicate_inactive_archived_and_mismatched_members_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
        $student = $this->student();
        $teacher = $this->teacher();
        foreach ([$student, $teacher] as $person) {
            $this->post(route('library.members.store'), $this->data($person))->assertSessionHasNoErrors();
            $this->post(route('library.members.store'), $this->data($person))->assertSessionHasErrors($person instanceof Student ? 'student_id' : 'teacher_id');
        }
        $inactive = $this->student(['status' => 'Inactive', 'first_name' => 'Inactive Student']);
        $archived = $this->student(['first_name' => 'Archived Student']);
        $archived->delete();
        $this->post(route('library.members.store'), $this->data($inactive))->assertSessionHasErrors('student_id');
        $this->post(route('library.members.store'), $this->data($archived))->assertSessionHasErrors('student_id');
        $this->post(route('library.members.store'), array_replace($this->data($teacher), ['student_id' => $inactive->id]))->assertSessionHasErrors('student_id');
        $this->post(route('library.members.store'), ['member_type' => 'Student', 'registration_date' => 'bad', 'borrowing_limit' => 0, 'status' => 'bad'])->assertSessionHasErrors(['student_id', 'registration_date', 'borrowing_limit', 'status']);
        $this->assertDatabaseCount('library_members', 2);
        $this->get(route('library.members.create'))->assertOk()->assertDontSee('Inactive Student')->assertDontSee('Archived Student')
            ->assertDontSee('Ali Hassan')->assertDontSee('Ayesha Khan');
    }

    public function test_members_can_be_updated_searched_and_filtered_without_changing_the_borrower(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
        $student = $this->student();
        $this->post(route('library.members.store'), $this->data($student))->assertSessionHasNoErrors();
        $member = LibraryMember::firstOrFail();
        $this->post(route('library.members.store'), $this->data($this->teacher()))->assertSessionHasNoErrors();
        $this->put(route('library.members.update', $member), ['registration_date' => '2026-10-08', 'borrowing_limit' => 7, 'status' => 'Suspended'])->assertSessionHasNoErrors();
        $this->get(route('library.members.edit', $member))->assertOk()->assertSee('value="7"', false);
        $this->assertSame('Suspended', $member->fresh()->status);
        $this->put(route('library.members.update', $member), ['registration_date' => '2026-10-08', 'borrowing_limit' => 7, 'status' => 'Active', 'member_type' => 'Teacher', 'teacher_id' => 1])->assertSessionHasErrors(['member_type', 'teacher_id']);
        $this->assertSame($student->id, $member->fresh()->student_id);
        foreach (['Ali Hassan', $student->admission_no, $member->member_id, $student->phone] as $search) {
            $this->get(route('library.members.index', ['search' => $search]))->assertOk()->assertViewHas('members', fn ($rows) => $rows->total() === 1);
        }
        $this->get(route('library.members.index', ['member_type' => 'Teacher', 'status' => 'Active', 'per_page' => 25]))->assertOk()->assertSee('Ayesha Khan')->assertDontSee('Ali Hassan');
        $student->update(['first_name' => 'Ali Updated']);
        $student->delete();
        $this->get(route('library.members.show', $member))->assertOk()->assertSee('Ali Updated Hassan');
    }

    public function test_librarians_manage_members_and_students_and_teachers_only_view_their_own_details(): void
    {
        $librarian = User::factory()->create(['role' => 'Librarian']);
        $librarian->assignRole(Role::findOrCreate('Librarian', 'web'));
        $studentUser = User::factory()->create(['role' => 'Student']);
        $teacherUser = User::factory()->create(['role' => 'Teacher']);
        $student = $this->student(['user_id' => $studentUser->id]);
        $teacher = $this->teacher(['user_id' => $teacherUser->id]);
        $this->actingAs($librarian)->get(route('library.members.create'))->assertOk();
        foreach ([$student, $teacher] as $person) {
            $this->post(route('library.members.store'), $this->data($person))->assertSessionHasNoErrors();
        }
        $members = LibraryMember::orderBy('id')->get();
        $this->get(route('library.members.index'))->assertOk()->assertSee('Members List');
        $this->put(route('library.members.update', $members[0]), ['registration_date' => '2026-10-07', 'borrowing_limit' => 4, 'status' => 'Inactive'])->assertSessionHasNoErrors();
        foreach ([[$studentUser, $members[0], $members[1]], [$teacherUser, $members[1], $members[0]]] as [$user, $own, $other]) {
            $this->actingAs($user)->get(route('library.members.show', $own))->assertOk()->assertDontSee('Edit Member');
            $this->get(route('library.members.show', $other))->assertForbidden();
            $this->get(route('library.members.index'))->assertForbidden();
            $this->get(route('library.members.create'))->assertForbidden();
            $this->get(route('library.members.edit', $own))->assertForbidden();
            $this->post(route('library.members.store'), $this->data($student))->assertForbidden();
            $this->put(route('library.members.update', $own), ['status' => 'Active'])->assertForbidden();
        }
        $unlinked = User::factory()->create(['role' => 'Student']);
        $this->actingAs($unlinked)->get(route('library.members.show', $members[0]))->assertForbidden();
        auth()->logout();
        $this->get(route('library.members.index'))->assertRedirect(route('login'));
    }
}

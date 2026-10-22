<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamSubject;
use App\Models\FeeVoucher;
use App\Models\Holiday;
use App\Models\LibraryBook;
use App\Models\LibraryMember;
use App\Models\LibraryTransaction;
use App\Models\Period;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Parent', 'guard_name' => 'web']);
    }

    private function createFullStudentContext(): array
    {
        $user = User::factory()->create([
            'name' => 'Ali Student',
            'email' => 'ali.student@test.com',
            'role' => 'Student',
        ]);
        $user->assignRole('Student');

        $class = SchoolClass::create(['name' => 'Grade 10']);
        $section = SchoolSection::create(['name' => 'Section A']);

        $student = Student::create([
            'user_id' => $user->id,
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'father_name' => 'Tariq Khan',
            'phone' => '03001234567',
            'address' => 'Street 1, Lahore',
            'date_of_birth' => '2010-01-01',
            'gender' => 'Male',
            'admission_no' => 'ADM-1001',
            'roll_number' => '12',
            'class_id' => $class->id,
            'section_id' => $section->id,
            'admission_date' => now()->toDateString(),
            'status' => 'Active',
        ]);

        return [$user, $student, $class, $section];
    }

    public function test_guest_is_redirected_from_dashboard_api(): void
    {
        $response = $this->get('/student/dashboard');
        $response->assertRedirect(route('portal.login'));
    }

    public function test_student_dashboard_api_returns_correct_json_structure(): void
    {
        [$user, $student, $class, $section] = $this->createFullStudentContext();

        // 1. Create Attendance for current month
        $today = now()->startOfMonth()->addDays(2)->toDateString();
        StudentAttendance::create([
            'student_id' => $student->id,
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'date' => $today,
            'status' => 'present',
        ]);
        StudentAttendance::create([
            'student_id' => $student->id,
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'date' => now()->startOfMonth()->addDays(3)->toDateString(),
            'status' => 'absent',
        ]);

        // 2. Create an event / notice
        SchoolEvent::create([
            'title' => 'Annual Sports Day',
            'description' => 'Sports day begins next week.',
            'date' => now()->addDays(5)->toDateString(),
            'type' => 'event',
        ]);

        // 3. Create Library member and transaction
        $author = \App\Models\LibraryAuthor::create(['name' => 'Newton']);
        $book = LibraryBook::create([
            'title' => 'Physics Primer',
            'isbn' => '978-3-16-148410-0',
            'author_id' => $author->id,
        ]);
        $member = LibraryMember::create([
            'member_type' => 'Student',
            'student_id' => $student->id,
            'registration_date' => now()->toDateString(),
            'borrowing_limit' => 3,
            'status' => 'Active',
        ]);
        $copy = \App\Models\LibraryBookCopy::create([
            'book_id' => $book->id,
            'accession_number' => 'ACC-001',
            'status' => 'Available',
        ]);
        LibraryTransaction::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'book_copy_id' => $copy->id,
            'issue_date' => now()->subDays(5)->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'Issued',
            'created_by' => $user->id,
        ]);

        // 4. Create an Exam schedule
        $subject = Subject::create(['name' => 'Physics', 'code' => 'PHY101']);
        $session = \App\Models\AcademicSession::create(['name' => '2026-2027']);
        $exam = Exam::create([
            'name' => 'Mid Term 2026',
            'type' => 'term',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'session_id' => $session->id,
            'status' => 'scheduled',
            'created_by' => $user->id,
        ]);
        $examSubject = ExamSubject::create([
            'exam_id' => $exam->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'total_marks' => 100,
            'theory_marks' => 100,
            'passing_marks' => 33,
        ]);
        ExamSchedule::create([
            'exam_subject_id' => $examSubject->id,
            'section_id' => $section->id,
            'exam_date' => now()->addDays(12)->toDateString(),
            'paper_deadline' => now()->addDays(5)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'room' => 'Hall 1',
        ]);

        // Make API request
        $response = $this->actingAs($user)->getJson('/student/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'student' => ['full_name', 'class', 'section', 'roll_no', 'admission_no', 'profile_photo_url'],
            'attendance' => ['present', 'absent', 'late', 'leave', 'total_working_days', 'percentage'],
            'fees' => ['status', 'total_pending_amount', 'latest_unpaid_challan'],
            'exams' => [
                'count',
                'upcoming' => [
                    '*' => ['subject', 'date', 'time', 'room'],
                ],
            ],
            'library' => ['issued_count', 'overdue_count'],
            'notice' => [
                '*' => ['id', 'title', 'description', 'date', 'type'],
            ],
            'todayClasses',
            'activeQuiz',
            'latestMaterial',
        ]);

        $data = $response->json();
        $this->assertEquals('Ali Khan', $data['student']['full_name']);
        $this->assertEquals('Grade 10', $data['student']['class']);
        $this->assertEquals('ADM-1001', $data['student']['admission_no']);

        $this->assertEquals(1, $data['attendance']['present']);
        $this->assertEquals(1, $data['attendance']['absent']);
        $this->assertEquals(2, $data['attendance']['total_working_days']);
        $this->assertEquals(50.0, $data['attendance']['percentage']);

        $this->assertEquals('paid', $data['fees']['status']);
        $this->assertEquals(0, $data['fees']['total_pending_amount']);
        $this->assertNull($data['fees']['latest_unpaid_challan']);

        $this->assertEquals(1, $data['exams']['count']);
        $this->assertEquals('Physics', $data['exams']['upcoming'][0]['subject']);
        $this->assertEquals('Hall 1', $data['exams']['upcoming'][0]['room']);

        $this->assertEquals(1, $data['library']['issued_count']);
        $this->assertEquals(0, $data['library']['overdue_count']);

        $this->assertCount(1, $data['notice']);
        $this->assertEquals('Annual Sports Day', $data['notice'][0]['title']);

        $this->assertNull($data['activeQuiz']);
        $this->assertIsArray($data['latestMaterial']);
    }

    public function test_security_rule_student_id_cannot_be_overridden_via_query_parameter(): void
    {
        [$user1, $student1] = $this->createFullStudentContext();

        $user2 = User::factory()->create(['role' => 'Student']);
        $user2->assignRole('Student');
        $student2 = Student::create([
            'user_id' => $user2->id,
            'first_name' => 'Victim',
            'last_name' => 'Person',
            'father_name' => 'Father',
            'phone' => '03000000000',
            'date_of_birth' => '2010-01-01',
            'gender' => 'Male',
            'address' => 'Victim Address',
            'roll_number' => '99',
            'admission_no' => 'ADM-VICTIM-99',
            'admission_date' => now()->toDateString(),
            'status' => 'Active',
        ]);

        // Attempt attack: user1 calls /student/dashboard?student_id=student2->id
        $response = $this->actingAs($user1)->getJson('/student/dashboard?student_id=' . $student2->id);

        $response->assertStatus(200);
        $data = $response->json();
        // Guaranteed: only student1 is returned
        $this->assertEquals('Ali Khan', $data['student']['full_name']);
        $this->assertEquals('ADM-1001', $data['student']['admission_no']);
        $this->assertNotEquals('ADM-VICTIM-99', $data['student']['admission_no']);
    }

    public function test_today_classes_returns_empty_array_on_holiday(): void
    {
        [$user, $student, $class, $section] = $this->createFullStudentContext();

        // Mark today as holiday
        Holiday::create([
            'name' => 'National Holiday',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
        ]);

        $response = $this->actingAs($user)->getJson('/student/dashboard');
        $response->assertStatus(200);
        $this->assertSame([], $response->json('todayClasses'));
    }

    public function test_parent_accesses_dashboard_api_for_active_child(): void
    {
        $parent = User::factory()->create(['name' => 'Parent User', 'role' => 'Parent']);
        $parent->assignRole('Parent');

        $class = SchoolClass::create(['name' => 'Grade 9']);
        $section = SchoolSection::create(['name' => 'Section B']);

        $child1 = Student::create([
            'first_name' => 'First',
            'last_name' => 'Child',
            'father_name' => 'Parent User',
            'phone' => '03001111111',
            'address' => 'Home Address',
            'date_of_birth' => '2011-01-01',
            'gender' => 'Male',
            'admission_no' => 'ADM-CHILD-1',
            'roll_number' => '01',
            'class_id' => $class->id,
            'section_id' => $section->id,
            'admission_date' => now()->toDateString(),
            'status' => 'Active',
        ]);

        $child2 = Student::create([
            'first_name' => 'Second',
            'last_name' => 'Child',
            'father_name' => 'Parent User',
            'phone' => '03001111111',
            'address' => 'Home Address',
            'date_of_birth' => '2013-01-01',
            'gender' => 'Female',
            'admission_no' => 'ADM-CHILD-2',
            'roll_number' => '02',
            'class_id' => $class->id,
            'section_id' => $section->id,
            'admission_date' => now()->toDateString(),
            'status' => 'Active',
        ]);

        $parent->children()->attach([$child1->id, $child2->id]);

        // Default child is child 1
        $resp1 = $this->actingAs($parent)->getJson('/student/dashboard');
        $resp1->assertStatus(200);
        $this->assertEquals('First Child', $resp1->json('student.full_name'));
        $this->assertEquals('ADM-CHILD-1', $resp1->json('student.admission_no'));

        // Parent switches to child 2
        $this->actingAs($parent)->withSession(['active_student_id' => $child2->id]);
        $resp2 = $this->actingAs($parent)->withSession(['active_student_id' => $child2->id])->getJson('/student/dashboard');
        $resp2->assertStatus(200);
        $this->assertEquals('Second Child', $resp2->json('student.full_name'));
        $this->assertEquals('ADM-CHILD-2', $resp2->json('student.admission_no'));
    }
}

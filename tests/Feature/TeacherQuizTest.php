<?php

namespace Tests\Feature;

use App\Models\GradeScale;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\TeacherPanelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherQuizTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GradeScaleSeeder::class);
    }

    protected function demo(): array
    {
        $this->seed(TeacherPanelSeeder::class);
        $user = User::where('email', 'teacher@test.com')->firstOrFail();
        $assignment = TeacherAssignment::where('teacher_id', $user->teacher->id)->firstOrFail();

        return [$user, $assignment];
    }

    public function test_teacher_can_view_quizzes_index_and_create_form(): void
    {
        [$teacher, $assignment] = $this->demo();

        $this->actingAs($teacher)
            ->get('/teacher/quizzes')
            ->assertOk()
            ->assertSee('Quizzes & Results')
            ->assertSee('Create Quiz');

        $this->get('/teacher/quizzes/create')
            ->assertOk()
            ->assertSee('Create New Assessment')
            ->assertSee($assignment->schoolClass->name);
    }

    public function test_formal_exam_setup_is_admin_only_and_quiz_shortcuts_remain_available(): void
    {
        [$teacher, $assignment] = $this->demo();
        $this->actingAs($teacher)->get('/teacher/dashboard')->assertOk()
            ->assertSee('Quizzes')->assertSee('My Examinations');
        $this->get('/teacher/classes/'.$assignment->id.'/quiz')
            ->assertRedirect(route('teacher.quizzes.index', [
                'class_id'=>$assignment->class_id, 'section_id'=>$assignment->section_id,
                'subject_id'=>$assignment->subject_id,
            ]));
        $this->get('/exams')->assertForbidden();
        foreach (['/exams/results', '/exams/approvals',
            '/teacher/exams/results', '/teacher/exams/marks', '/portal/results'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Examinations');
        $this->get('/exams')->assertOk();
    }

    public function test_teacher_can_create_a_valid_quiz(): void
    {
        [$teacher, $assignment] = $this->demo();

        $response = $this->actingAs($teacher)->post('/teacher/quizzes', [
            'assignment_id' => $assignment->id,
            'title' => 'Unit 1 Vocabulary Quiz',
            'type' => 'quiz',
            'date' => today()->toDateString(),
            'total_marks' => 20,
            'passing_marks' => 10,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('quizzes', [
            'title' => 'Unit 1 Vocabulary Quiz',
            'total_marks' => 20.00,
            'passing_marks' => 10.00,
            'teacher_id' => $teacher->teacher->id,
            'class_id' => $assignment->class_id,
        ]);
    }

    public function test_passing_marks_cannot_exceed_total_marks(): void
    {
        [$teacher, $assignment] = $this->demo();

        $response = $this->actingAs($teacher)->post('/teacher/quizzes', [
            'assignment_id' => $assignment->id,
            'title' => 'Invalid Marks Quiz',
            'type' => 'quiz',
            'date' => today()->toDateString(),
            'total_marks' => 20,
            'passing_marks' => 25, // Exceeds total
        ]);

        $response->assertSessionHasErrors('passing_marks');
        $this->assertDatabaseMissing('quizzes', ['title' => 'Invalid Marks Quiz']);
    }

    public function test_teacher_cannot_create_or_view_quiz_for_unassigned_class(): void
    {
        [$teacher, $assignment] = $this->demo();

        $otherClass = SchoolClass::create(['name' => 'Unassigned Class']);
        $otherUser = User::factory()->create(['role' => 'Teacher']);
        $otherTeacher = $assignment->teacher->replicate(['employee_id', 'cnic', 'user_id']);
        $otherTeacher->fill(['employee_id' => 'OTHER-002', 'cnic' => '99999-0000002-1', 'user_id' => $otherUser->id])->save();

        $unassignedQuiz = Quiz::create([
            'title' => 'Other Teacher Quiz',
            'type' => 'test',
            'class_id' => $otherClass->id,
            'section_id' => $assignment->section_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $otherTeacher->id,
            'session_id' => $assignment->session_id,
            'date' => today(),
            'total_marks' => 50,
            'passing_marks' => 20,
            'status' => 'published',
        ]);

        $this->actingAs($teacher)
            ->get('/teacher/quizzes/'.$unassignedQuiz->id)
            ->assertForbidden();

        $this->get('/teacher/quizzes/'.$unassignedQuiz->id.'/marks')
            ->assertForbidden();
    }

    public function test_teacher_can_enter_and_update_marks(): void
    {
        [$teacher, $assignment] = $this->demo();

        $quiz = Quiz::create([
            'title' => 'Math Midterm',
            'type' => 'midterm',
            'class_id' => $assignment->class_id,
            'section_id' => $assignment->section_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $assignment->teacher_id,
            'session_id' => $assignment->session_id,
            'date' => today(),
            'total_marks' => 100,
            'passing_marks' => 40,
            'status' => 'draft',
        ]);

        $students = Student::where('class_id', $assignment->class_id)->where('section_id', $assignment->section_id)->get();

        $marksData = [
            'action' => 'draft',
            'rows' => [
                [
                    'student_id' => $students[0]->id,
                    'attendance_status' => 'present',
                    'marks_obtained' => 85,
                    'remarks' => 'Good job',
                ],
                [
                    'student_id' => $students[1]->id,
                    'attendance_status' => 'absent',
                    'marks_obtained' => null,
                    'remarks' => 'Sick',
                ],
            ],
        ];

        $response = $this->actingAs($teacher)->post('/teacher/quizzes/'.$quiz->id.'/marks', $marksData);
        $response->assertRedirect();

        $this->assertDatabaseHas('quiz_results', [
            'quiz_id' => $quiz->id,
            'student_id' => $students[0]->id,
            'marks_obtained' => 85.00,
            'attendance_status' => 'present',
        ]);

        $this->assertDatabaseHas('quiz_results', [
            'quiz_id' => $quiz->id,
            'student_id' => $students[1]->id,
            'attendance_status' => 'absent',
        ]);
    }

    public function test_cannot_delete_quiz_with_entered_marks(): void
    {
        [$teacher, $assignment] = $this->demo();

        $quiz = Quiz::create([
            'title' => 'Protected Quiz',
            'type' => 'quiz',
            'class_id' => $assignment->class_id,
            'section_id' => $assignment->section_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $assignment->teacher_id,
            'session_id' => $assignment->session_id,
            'date' => today(),
            'total_marks' => 50,
            'passing_marks' => 20,
            'status' => 'draft',
        ]);

        $student = Student::where('class_id', $assignment->class_id)->first();
        QuizResult::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'marks_obtained' => 45,
            'attendance_status' => 'present',
            'entered_by' => $teacher->id,
        ]);

        $this->actingAs($teacher)
            ->delete('/teacher/quizzes/'.$quiz->id)
            ->assertForbidden();

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id]);
    }

    public function test_can_delete_empty_quiz(): void
    {
        [$teacher, $assignment] = $this->demo();

        $quiz = Quiz::create([
            'title' => 'Empty Quiz',
            'type' => 'quiz',
            'class_id' => $assignment->class_id,
            'section_id' => $assignment->section_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $assignment->teacher_id,
            'session_id' => $assignment->session_id,
            'date' => today(),
            'total_marks' => 50,
            'passing_marks' => 20,
            'status' => 'draft',
        ]);

        $this->actingAs($teacher)
            ->delete('/teacher/quizzes/'.$quiz->id)
            ->assertRedirect('/teacher/quizzes');

        $this->assertDatabaseMissing('quizzes', ['id' => $quiz->id]);
    }
}

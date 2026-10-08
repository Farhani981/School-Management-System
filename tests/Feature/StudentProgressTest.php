<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\GradeScale;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentNote;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\StudentProgressService;
use Database\Seeders\GradeScaleSeeder;
use Database\Seeders\TeacherPanelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentProgressTest extends TestCase
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
        $students = Student::where('class_id', $assignment->class_id)
            ->where('section_id', $assignment->section_id)
            ->orderBy('id')
            ->get();

        return [$user, $assignment, $students];
    }

    public function test_teacher_can_view_student_progress_page(): void
    {
        [$teacher, $assignment, $students] = $this->demo();

        $this->actingAs($teacher)
            ->get('/teacher/progress?student='.$students[0]->id)
            ->assertOk()
            ->assertSee('Student Progress')
            ->assertSee('Track how each student is doing.')
            ->assertSee($students[0]->full_name)
            ->assertSee('Assessments')
            ->assertSee('Academic Average')
            ->assertSee('Attendance %');
    }

    public function test_teacher_cannot_open_student_from_unassigned_section(): void
    {
        [$teacher, $assignment] = $this->demo();

        $otherClass = SchoolClass::create(['name' => 'Other Class']);
        $otherSection = SchoolSection::create(['name' => 'Other Section']);
        $otherStudent = Student::create([
            'admission_no' => 'OTHER-001',
            'first_name' => 'Forbidden',
            'last_name' => 'Student',
            'father_name' => 'Other Parent',
            'gender' => 'Male',
            'phone' => '03001111111',
            'address' => 'Test Address',
            'class_id' => $otherClass->id,
            'section_id' => $otherSection->id,
            'academic_session_id' => $assignment->session_id,
            'roll_number' => '99',
            'status' => 'Active',
            'date_of_birth' => '2018-01-01',
            'admission_date' => today(),
        ]);

        $this->actingAs($teacher)
            ->get('/teacher/progress?student='.$otherStudent->id)
            ->assertForbidden();
    }

    public function test_status_rule_at_risk_needs_attention_and_on_track(): void
    {
        $service = app(StudentProgressService::class);

        // 1. Not enough data
        $statusNoData = $service->determineStudentStatus(null, null, null, 0, 0, 0);
        $this->assertSame('no_data', $statusNoData['state']);
        $this->assertSame('Not enough data', $statusNoData['label']);

        // 2. At risk: Attendance < 75%
        $statusLowAtt = $service->determineStudentStatus(72.5, 80.0, 75.0, 0, 5, 20);
        $this->assertSame('at_risk', $statusLowAtt['state']);
        $this->assertStringContainsString('Attendance is 72.5%', $statusLowAtt['reason']);

        // 3. At risk: Academic average < 40%
        $statusLowGrade = $service->determineStudentStatus(90.0, 38.0, 70.0, 0, 5, 20);
        $this->assertSame('at_risk', $statusLowGrade['state']);
        $this->assertStringContainsString('Academic average is 38%', $statusLowGrade['reason']);

        // 4. Needs attention: Attendance between 75% and 85%
        $statusMidAtt = $service->determineStudentStatus(81.0, 75.0, 70.0, 0, 5, 20);
        $this->assertSame('needs_attention', $statusMidAtt['state']);
        $this->assertStringContainsString('Attendance is 81%', $statusMidAtt['reason']);

        // 5. Needs attention: Average >= 15 points below class average
        $statusGap = $service->determineStudentStatus(95.0, 50.0, 68.0, 0, 5, 20); // 18 pts below
        $this->assertSame('needs_attention', $statusGap['state']);
        $this->assertStringContainsString('18 points below class average', $statusGap['reason']);

        // 6. Needs attention: 3 consecutive drops
        $statusDrops = $service->determineStudentStatus(95.0, 65.0, 68.0, 3, 5, 20);
        $this->assertSame('needs_attention', $statusDrops['state']);
        $this->assertStringContainsString('3 consecutive score drops', $statusDrops['reason']);

        // 7. On track: Good attendance and good grade
        $statusOnTrack = $service->determineStudentStatus(92.0, 78.0, 75.0, 0, 5, 20);
        $this->assertSame('on_track', $statusOnTrack['state']);
        $this->assertSame('On track', $statusOnTrack['label']);
    }

    public function test_absent_and_exempt_are_excluded_from_averages_and_pass_counts(): void
    {
        [$teacher, $assignment, $students] = $this->demo();
        $targetStudent = $students[0];

        $quiz1 = Quiz::create([
            'title' => 'Quiz 1',
            'type' => 'quiz',
            'class_id' => $assignment->class_id,
            'section_id' => $assignment->section_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $assignment->teacher_id,
            'session_id' => $assignment->session_id,
            'date' => today()->subDays(5),
            'total_marks' => 100,
            'passing_marks' => 40,
            'status' => 'published',
        ]);

        $quiz2 = Quiz::create([
            'title' => 'Quiz 2',
            'type' => 'quiz',
            'class_id' => $assignment->class_id,
            'section_id' => $assignment->section_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $assignment->teacher_id,
            'session_id' => $assignment->session_id,
            'date' => today()->subDays(3),
            'total_marks' => 100,
            'passing_marks' => 40,
            'status' => 'published',
        ]);

        $quiz3 = Quiz::create([
            'title' => 'Quiz 3',
            'type' => 'quiz',
            'class_id' => $assignment->class_id,
            'section_id' => $assignment->section_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $assignment->teacher_id,
            'session_id' => $assignment->session_id,
            'date' => today()->subDay(),
            'total_marks' => 100,
            'passing_marks' => 40,
            'status' => 'published',
        ]);

        // Student attended Quiz 1 with 80%
        QuizResult::create([
            'quiz_id' => $quiz1->id,
            'student_id' => $targetStudent->id,
            'marks_obtained' => 80,
            'attendance_status' => 'present',
            'entered_by' => $teacher->id,
        ]);

        // Student absent for Quiz 2
        QuizResult::create([
            'quiz_id' => $quiz2->id,
            'student_id' => $targetStudent->id,
            'marks_obtained' => null,
            'attendance_status' => 'absent',
            'entered_by' => $teacher->id,
        ]);

        // Student exempt for Quiz 3
        QuizResult::create([
            'quiz_id' => $quiz3->id,
            'student_id' => $targetStudent->id,
            'marks_obtained' => null,
            'attendance_status' => 'exempt',
            'entered_by' => $teacher->id,
        ]);

        $service = app(StudentProgressService::class);
        $data = $service->getStudentProgressData($targetStudent, $assignment->session_id);

        // Academic average must strictly be 80.0% (not (80+0+0)/3 = 26.6%)
        $this->assertEquals(80.0, $data['summary']['academic_average']);
        $this->assertEquals(1, $data['summary']['assessments_count']);
        $this->assertEquals(1, $data['summary']['passed_count']);
    }

    public function test_class_rank_ties_share_rank(): void
    {
        [$teacher, $assignment, $students] = $this->demo();

        $quiz = Quiz::create([
            'title' => 'Session Exam',
            'type' => 'test',
            'class_id' => $assignment->class_id,
            'section_id' => $assignment->section_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $assignment->teacher_id,
            'session_id' => $assignment->session_id,
            'date' => today(),
            'total_marks' => 100,
            'passing_marks' => 40,
            'status' => 'published',
        ]);

        // Student 1 & Student 2 get 90 (tie for 1st)
        QuizResult::create(['quiz_id' => $quiz->id, 'student_id' => $students[0]->id, 'marks_obtained' => 90, 'attendance_status' => 'present', 'entered_by' => $teacher->id]);
        QuizResult::create(['quiz_id' => $quiz->id, 'student_id' => $students[1]->id, 'marks_obtained' => 90, 'attendance_status' => 'present', 'entered_by' => $teacher->id]);
        // Student 3 gets 80 (3rd place)
        QuizResult::create(['quiz_id' => $quiz->id, 'student_id' => $students[2]->id, 'marks_obtained' => 80, 'attendance_status' => 'present', 'entered_by' => $teacher->id]);

        $service = app(StudentProgressService::class);
        $rank1 = $service->calculateClassRank($students[0], $assignment->session_id);
        $rank2 = $service->calculateClassRank($students[1], $assignment->session_id);
        $rank3 = $service->calculateClassRank($students[2], $assignment->session_id);

        $this->assertEquals(1, $rank1['rank']);
        $this->assertEquals(1, $rank2['rank']); // Tied for rank 1
        $this->assertEquals(3, $rank3['rank']); // Next rank is 3
    }

    public function test_trend_calculation_with_flat_boundary(): void
    {
        [$teacher, $assignment, $students] = $this->demo();
        $student = $students[0];

        $quizA = Quiz::create([
            'title' => 'Test A',
            'type' => 'test',
            'class_id' => $assignment->class_id,
            'section_id' => $assignment->section_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $assignment->teacher_id,
            'session_id' => $assignment->session_id,
            'date' => today()->subDays(4),
            'total_marks' => 100,
            'passing_marks' => 40,
            'status' => 'published',
        ]);

        $quizB = Quiz::create([
            'title' => 'Test B',
            'type' => 'test',
            'class_id' => $assignment->class_id,
            'section_id' => $assignment->section_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $assignment->teacher_id,
            'session_id' => $assignment->session_id,
            'date' => today()->subDays(2),
            'total_marks' => 100,
            'passing_marks' => 40,
            'status' => 'published',
        ]);

        // Difference: 71 - 70 = 1.0% (Under 2.0% -> flat)
        QuizResult::create(['quiz_id' => $quizA->id, 'student_id' => $student->id, 'marks_obtained' => 70, 'attendance_status' => 'present', 'entered_by' => $teacher->id]);
        $resB = QuizResult::create(['quiz_id' => $quizB->id, 'student_id' => $student->id, 'marks_obtained' => 71, 'attendance_status' => 'present', 'entered_by' => $teacher->id]);

        $service = app(StudentProgressService::class);
        $data = $service->getStudentProgressData($student, $assignment->session_id);
        $this->assertSame('flat', $data['summary']['trend']['direction']);

        // Now difference: 76 - 70 = 6.0% (>= 2.0% -> up)
        $resB->update(['marks_obtained' => 76]);
        $dataUp = $service->getStudentProgressData($student, $assignment->session_id);
        $this->assertSame('up', $dataUp['summary']['trend']['direction']);

        // Now difference: 65 - 70 = -5.0% (<= -2.0% -> down)
        $resB->update(['marks_obtained' => 65]);
        $dataDown = $service->getStudentProgressData($student, $assignment->session_id);
        $this->assertSame('down', $dataDown['summary']['trend']['direction']);
    }

    public function test_teacher_notes_crud_and_privacy(): void
    {
        [$teacher, $assignment, $students] = $this->demo();
        $student = $students[0];

        // 1. Create Note
        $response = $this->actingAs($teacher)->post('/teacher/progress/'.$student->id.'/notes', [
            'note' => 'Needs extra support with algebra concepts.',
            'tag' => 'Needs support',
            'follow_up_date' => today()->addDays(5)->toDateString(),
            'session_id' => $assignment->session_id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('student_notes', [
            'student_id' => $student->id,
            'teacher_id' => $teacher->teacher->id,
            'tag' => 'Needs support',
            'is_done' => false,
        ]);

        $note = StudentNote::where('student_id', $student->id)->firstOrFail();

        // 2. Another teacher cannot update or delete this note
        $otherUser = User::factory()->create(['role' => 'Teacher']);
        $otherTeacher = $assignment->teacher->replicate(['employee_id', 'cnic', 'user_id']);
        $otherTeacher->fill(['employee_id' => 'OTHER-003', 'cnic' => '99999-0000004-1', 'user_id' => $otherUser->id])->save();

        $this->actingAs($otherUser)
            ->put('/teacher/progress/notes/'.$note->id, [
                'note' => 'Attempted hijack',
                'tag' => 'Behavior',
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete('/teacher/progress/notes/'.$note->id)
            ->assertForbidden();

        // 3. Author teacher can update and delete
        $this->actingAs($teacher)
            ->put('/teacher/progress/notes/'.$note->id, [
                'note' => 'Parent was contacted; meeting scheduled.',
                'tag' => 'Parent contact',
                'is_done' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('student_notes', [
            'id' => $note->id,
            'tag' => 'Parent contact',
            'is_done' => true,
        ]);

        $this->actingAs($teacher)
            ->delete('/teacher/progress/notes/'.$note->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('student_notes', ['id' => $note->id]);
    }

    public function test_class_overview_and_needs_attention_list(): void
    {
        [$teacher, $assignment, $students] = $this->demo();

        // Mark Student 1 with low attendance (e.g. 50%)
        StudentAttendance::create(['student_id' => $students[0]->id, 'academic_session_id' => $assignment->session_id, 'school_class_id' => $assignment->class_id, 'section_id' => $assignment->section_id, 'date' => today()->subDays(2), 'status' => 'present', 'marked_by' => $teacher->id]);
        StudentAttendance::create(['student_id' => $students[0]->id, 'academic_session_id' => $assignment->session_id, 'school_class_id' => $assignment->class_id, 'section_id' => $assignment->section_id, 'date' => today()->subDay(), 'status' => 'absent', 'marked_by' => $teacher->id]);

        $response = $this->actingAs($teacher)->get('/teacher/progress?class_id='.$assignment->class_id.'&section_id='.$assignment->section_id.'&tab=class');
        $response->assertOk();
        $response->assertSee('Subject Mastery Heatmap');
        $response->assertSee('Students Requiring Teacher Attention');
        $response->assertSee($students[0]->full_name);
    }

    public function test_exports_generate_valid_responses(): void
    {
        [$teacher, $assignment, $students] = $this->demo();

        // PDF Export
        $pdfResponse = $this->actingAs($teacher)->get('/teacher/progress/'.$students[0]->id.'/export/pdf?comment=Doing+well');
        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', $pdfResponse->headers->get('Content-Type'));

        // Class CSV Export
        $csvResponse = $this->actingAs($teacher)->get('/teacher/progress/export/class?class_id='.$assignment->class_id.'&section_id='.$assignment->section_id.'&session_id='.$assignment->session_id);
        $csvResponse->assertOk();
        $this->assertStringContainsString('text/csv', $csvResponse->headers->get('Content-Type'));
    }
}

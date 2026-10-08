<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\StudentNote;
use App\Services\StudentProgressService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentProgressController extends Controller
{
    public function __construct(
        protected StudentProgressService $progressService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $teacher = $user->teacher;

        if (! $teacher || $teacher->status !== 'Active') {
            abort(403, 'Active teacher account required.');
        }

        $assignedSections = $this->progressService->getAssignedClassSections($teacher);

        if (empty($assignedSections)) {
            return view('teacher.progress.index', [
                'assignedSections' => [],
                'selectedClassId' => null,
                'selectedSectionId' => null,
                'activeTab' => 'class',
                'studentData' => null,
                'classData' => null,
                'roster' => collect(),
            ]);
        }

        // 1. Determine active class & section
        $selectedClassId = (int) $request->get('class_id', $assignedSections[0]['class_id']);
        $selectedSectionId = (int) $request->get('section_id', $assignedSections[0]['section_id']);

        // Verify teacher owns this section
        $validSection = collect($assignedSections)->first(
            fn ($s) => $s['class_id'] === $selectedClassId && $s['section_id'] === $selectedSectionId
        );

        if (! $validSection) {
            $selectedClassId = $assignedSections[0]['class_id'];
            $selectedSectionId = $assignedSections[0]['section_id'];
        }

        $roster = $this->progressService->getRoster($selectedClassId, $selectedSectionId);
        $sessionId = (int) $request->get('session', $request->get('session_id', AcademicSession::where('is_current', true)->value('id')));

        // 2. Determine active tab & student
        $requestedStudentId = $request->get('student', $request->get('student_id'));
        $activeTab = $request->get('tab');

        $selectedStudent = null;
        if ($requestedStudentId) {
            $candidate = Student::find((int) $requestedStudentId);
            if ($candidate && $this->progressService->isTeacherAssignedToStudent($teacher, $candidate)) {
                $selectedStudent = $candidate;
                // Switch class and section to match this student
                $selectedClassId = $selectedStudent->class_id;
                $selectedSectionId = $selectedStudent->section_id;
                $roster = $this->progressService->getRoster($selectedClassId, $selectedSectionId);
                if (! $activeTab) {
                    $activeTab = 'student';
                }
            } else {
                abort(403, 'Unauthorized access to student.');
            }
        }

        if (! $activeTab) {
            $activeTab = $selectedStudent ? 'student' : 'class';
        }

        // If in student tab but no student chosen, default to first student in roster
        if ($activeTab === 'student' && ! $selectedStudent && $roster->isNotEmpty()) {
            $selectedStudent = $roster->first();
        }

        // 3. Compute Data
        $studentData = null;
        if ($selectedStudent) {
            $filters = [
                'subject_id' => $request->get('subject_id'),
                'type' => $request->get('type'),
                'timeframe' => $request->get('timeframe'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
            ];

            $studentData = $this->progressService->getStudentProgressData($selectedStudent, $sessionId, $filters);
        }

        $compareIds = array_filter((array) $request->get('compare', []));
        $classData = $this->progressService->getClassOverviewData($selectedClassId, $selectedSectionId, $sessionId, $compareIds);

        // Filter needs_attention list if filter=attention query is present (for dashboard link)
        $filterMode = $request->get('filter');

        return view('teacher.progress.index', [
            'assignedSections' => $assignedSections,
            'selectedClassId' => $selectedClassId,
            'selectedSectionId' => $selectedSectionId,
            'sessionId' => $sessionId,
            'roster' => $roster,
            'selectedStudent' => $selectedStudent,
            'activeTab' => $activeTab,
            'studentData' => $studentData,
            'classData' => $classData,
            'filterMode' => $filterMode,
        ]);
    }

    /**
     * Export single student progress report PDF.
     */
    public function exportPdf(Request $request, Student $student)
    {
        $teacher = $request->user()->teacher;
        if (! $teacher || ! $this->progressService->isTeacherAssignedToStudent($teacher, $student)) {
            abort(403, 'Unauthorized.');
        }

        $sessionId = (int) $request->get('session_id', $student->academic_session_id);
        $studentData = $this->progressService->getStudentProgressData($student, $sessionId);
        $teacherComment = $request->get('comment', $request->get('teacher_comment'));

        $pdf = Pdf::loadView('teacher.progress.pdf.student-report', [
            'student' => $student,
            'data' => $studentData,
            'teacherComment' => $teacherComment,
            'teacher' => $teacher,
            'date' => today()->format('d M Y'),
        ])->setPaper('a4', 'portrait');

        $safeName = \Illuminate\Support\Str::slug($student->full_name);
        return $pdf->download("progress-report-{$safeName}-{$student->admission_no}.pdf");
    }

    /**
     * Export Class Overview data as CSV.
     */
    public function exportClassCsv(Request $request): StreamedResponse
    {
        $teacher = $request->user()->teacher;
        $classId = (int) $request->get('class_id');
        $sectionId = (int) $request->get('section_id');

        $assigned = $this->progressService->getAssignedClassSections($teacher);
        $valid = collect($assigned)->first(fn ($s) => $s['class_id'] === $classId && $s['section_id'] === $sectionId);
        if (! $valid) {
            abort(403, 'Unauthorized class or section.');
        }

        $sessionId = (int) $request->get('session_id');
        $classData = $this->progressService->getClassOverviewData($classId, $sectionId, $sessionId);

        $className = $classData['schoolClass']?->name ?? 'Class';
        $sectionName = $classData['section']?->name ?? 'Section';
        $filename = "class-overview-{$className}-{$sectionName}.csv";

        return response()->streamDownload(function () use ($classData) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Roll No', 'Admission No', 'Student Name', 'Status', 'Attendance %', 'Academic Average %']);

            foreach ($classData['roster'] as $st) {
                $statusItem = collect($classData['needs_attention'])->firstWhere('student.id', $st->id);
                $statusLabel = $statusItem ? $statusItem['status']['label'] : 'On track';
                $avg = $statusItem['average'] ?? '–';
                $att = $statusItem['attendance'] ?? '–';

                fputcsv($handle, [
                    $st->roll_number,
                    $st->admission_no,
                    $st->full_name,
                    $statusLabel,
                    $att !== '–' ? $att . '%' : '–',
                    $avg !== '–' ? $avg . '%' : '–',
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Store new student note.
     */
    public function storeNote(Request $request, Student $student)
    {
        Gate::authorize('create', [StudentNote::class, $student]);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'tag' => ['required', 'string', 'in:Needs support,Gifted,Behavior,Parent contact,Other'],
            'follow_up_date' => ['nullable', 'date'],
            'session_id' => ['nullable', 'integer'],
        ]);

        $sessionId = $validated['session_id'] ?? ($student->academic_session_id ?: AcademicSession::where('is_current', true)->value('id'));

        StudentNote::create([
            'student_id' => $student->id,
            'teacher_id' => $request->user()->teacher->id,
            'session_id' => $sessionId,
            'note' => $validated['note'],
            'tag' => $validated['tag'],
            'follow_up_date' => $validated['follow_up_date'] ?? null,
            'is_done' => false,
        ]);

        return redirect()->route('teacher.progress.index', [
            'student' => $student->id,
            'tab' => 'student',
        ])->with('success', 'Teacher note saved successfully.');
    }

    /**
     * Update student note.
     */
    public function updateNote(Request $request, StudentNote $note)
    {
        Gate::authorize('update', $note);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'tag' => ['required', 'string', 'in:Needs support,Gifted,Behavior,Parent contact,Other'],
            'follow_up_date' => ['nullable', 'date'],
            'is_done' => ['nullable', 'boolean'],
        ]);

        $note->update([
            'note' => $validated['note'],
            'tag' => $validated['tag'],
            'follow_up_date' => $validated['follow_up_date'] ?? null,
            'is_done' => $request->boolean('is_done'),
        ]);

        return redirect()->route('teacher.progress.index', [
            'student' => $note->student_id,
            'tab' => 'student',
        ])->with('success', 'Note updated successfully.');
    }

    /**
     * Delete student note.
     */
    public function destroyNote(Request $request, StudentNote $note)
    {
        Gate::authorize('delete', $note);

        $studentId = $note->student_id;
        $note->delete();

        return redirect()->route('teacher.progress.index', [
            'student' => $studentId,
            'tab' => 'student',
        ])->with('success', 'Note deleted.');
    }
}

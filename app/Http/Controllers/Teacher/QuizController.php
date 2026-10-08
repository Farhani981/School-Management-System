<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreQuizRequest;
use App\Http\Requests\Teacher\UpdateQuizRequest;
use App\Models\Quiz;
use App\Models\TeacherAssignment;
use App\Services\QuizService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuizController extends Controller
{
    public function __construct(
        protected QuizService $quizService
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Quiz::class);

        $teacher = $request->user()->teacher;
        $assignments = $this->quizService->getTeacherAssignments($teacher);

        $filters = [
            'class_id' => $request->get('class_id'),
            'section_id' => $request->get('section_id'),
            'subject_id' => $request->get('subject_id'),
            'type' => $request->get('type'),
            'status' => $request->get('status'),
            'search' => $request->get('search'),
        ];

        $quizzes = $this->quizService->getFilteredQuizzes($teacher, $filters);
        $pendingCount = $this->quizService->getPendingMarksCount($teacher);

        return view('teacher.quizzes.index', [
            'quizzes' => $quizzes,
            'assignments' => $assignments,
            'filters' => $filters,
            'types' => Quiz::TYPES,
            'pendingCount' => $pendingCount,
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Quiz::class);

        $teacher = $request->user()->teacher;
        $assignments = $this->quizService->getTeacherAssignments($teacher);

        if ($assignments->isEmpty()) {
            return redirect()->route('teacher.quizzes.index')
                ->with('error', 'No classes or subjects are currently assigned to your account.');
        }

        // Pre-select assignment if class_id/section_id/subject_id passed from class cards
        $preselectedAssignmentId = null;
        if ($request->has('assignment_id')) {
            $preselectedAssignmentId = (int) $request->get('assignment_id');
        } elseif ($request->has('class_id') && $request->has('section_id')) {
            $matched = $assignments->first(function ($a) use ($request) {
                return $a->class_id == $request->get('class_id')
                    && $a->section_id == $request->get('section_id')
                    && (! $request->has('subject_id') || $a->subject_id == $request->get('subject_id'));
            });
            $preselectedAssignmentId = $matched?->id;
        }

        return view('teacher.quizzes.form', [
            'quiz' => new Quiz(['date' => today(), 'total_marks' => 20, 'passing_marks' => 8, 'type' => 'quiz']),
            'assignments' => $assignments,
            'types' => Quiz::TYPES,
            'isEdit' => false,
            'hasMarks' => false,
            'preselectedAssignmentId' => $preselectedAssignmentId,
        ]);
    }

    public function store(StoreQuizRequest $request)
    {
        $data = $request->validated();
        $assignment = TeacherAssignment::findOrFail($data['assignment_id']);
        $status = $request->input('action') === 'publish' ? 'published' : ($data['status'] ?? 'draft');

        $quiz = Quiz::create([
            'title' => $data['title'],
            'type' => $data['type'],
            'class_id' => $assignment->class_id,
            'section_id' => $assignment->section_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $request->user()->teacher->id,
            'session_id' => $assignment->session_id,
            'date' => $data['date'],
            'total_marks' => $data['total_marks'],
            'passing_marks' => $data['passing_marks'],
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
        ]);

        return redirect()->route('teacher.quizzes.marks', $quiz)
            ->with('success', 'Assessment created successfully. You can now enter student marks.');
    }

    public function show(Quiz $quiz)
    {
        Gate::authorize('view', $quiz);

        $stats = $this->quizService->calculateStatistics($quiz);
        $rankedStudents = $this->quizService->calculateRanks($quiz);
        $needsAttention = $this->quizService->getNeedsAttention($quiz, $rankedStudents, $stats);

        return view('teacher.quizzes.show', [
            'quiz' => $quiz->load(['schoolClass', 'section', 'subject', 'session', 'teacher']),
            'stats' => $stats,
            'rankedStudents' => $rankedStudents,
            'needsAttention' => $needsAttention,
        ]);
    }

    public function edit(Quiz $quiz, Request $request)
    {
        Gate::authorize('update', $quiz);

        $teacher = $request->user()->teacher;
        $assignments = $this->quizService->getTeacherAssignments($teacher);
        $hasMarks = $quiz->hasMarksEntered();

        $currentAssignment = $assignments->first(function ($a) use ($quiz) {
            return $a->class_id == $quiz->class_id
                && $a->section_id == $quiz->section_id
                && $a->subject_id == $quiz->subject_id
                && $a->session_id == $quiz->session_id;
        });

        return view('teacher.quizzes.form', [
            'quiz' => $quiz,
            'assignments' => $assignments,
            'types' => Quiz::TYPES,
            'isEdit' => true,
            'hasMarks' => $hasMarks,
            'preselectedAssignmentId' => $currentAssignment?->id,
        ]);
    }

    public function update(UpdateQuizRequest $request, Quiz $quiz)
    {
        $data = $request->validated();
        $hasMarks = $quiz->hasMarksEntered();

        $updateData = [
            'title' => $data['title'],
            'type' => $data['type'],
            'date' => $data['date'],
            'passing_marks' => $data['passing_marks'],
        ];

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
            if ($data['status'] === 'published' && ! $quiz->published_at) {
                $updateData['published_at'] = now();
            }
        }

        // Only update class & total marks if no marks have been entered yet
        if (! $hasMarks) {
            if (! empty($data['assignment_id'])) {
                $assignment = TeacherAssignment::findOrFail($data['assignment_id']);
                $updateData['class_id'] = $assignment->class_id;
                $updateData['section_id'] = $assignment->section_id;
                $updateData['subject_id'] = $assignment->subject_id;
                $updateData['session_id'] = $assignment->session_id;
            }
            if (! empty($data['total_marks'])) {
                $updateData['total_marks'] = $data['total_marks'];
            }
        }

        $quiz->update($updateData);

        return redirect()->route('teacher.quizzes.index')
            ->with('success', "Assessment '{$quiz->title}' updated successfully.");
    }

    public function duplicate(Quiz $quiz, Request $request)
    {
        Gate::authorize('duplicate', $quiz);

        $teacher = $request->user()->teacher;
        $duplicated = $this->quizService->duplicateQuiz($quiz, $teacher);

        return redirect()->route('teacher.quizzes.edit', $duplicated)
            ->with('success', "Assessment duplicated as '{$duplicated->title}'. You can adjust details before publishing.");
    }

    public function destroy(Quiz $quiz)
    {
        Gate::authorize('delete', $quiz);

        if ($quiz->hasMarksEntered()) {
            return redirect()->route('teacher.quizzes.index')
                ->with('error', 'Cannot delete assessment: marks have already been recorded for students.');
        }

        $title = $quiz->title;
        $quiz->delete();

        return redirect()->route('teacher.quizzes.index')
            ->with('success', "Assessment '{$title}' has been deleted.");
    }

    public function export(Quiz $quiz, string $format)
    {
        Gate::authorize('view', $quiz);

        $stats = $this->quizService->calculateStatistics($quiz);
        $rankedStudents = $this->quizService->calculateRanks($quiz);

        $filenameSafe = \Illuminate\Support\Str::slug("{$quiz->schoolClass->name}-{$quiz->section->name}-{$quiz->subject->name}-{$quiz->title}");

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filenameSafe}-results.csv\"",
            ];

            $callback = function () use ($quiz, $rankedStudents, $stats) {
                $file = fopen('php://output', 'w');
                // UTF-8 BOM for Excel
                fputs($file, "\xEF\xBB\xBF");

                fputcsv($file, ['Assessment Result Sheet']);
                fputcsv($file, ['Title', $quiz->title]);
                fputcsv($file, ['Class', $quiz->schoolClass->name . ' · ' . $quiz->section->name]);
                fputcsv($file, ['Subject', $quiz->subject->name]);
                fputcsv($file, ['Date', $quiz->date->format('d M Y')]);
                fputcsv($file, ['Total Marks', $quiz->total_marks]);
                fputcsv($file, ['Passing Marks', $quiz->passing_marks]);
                fputcsv($file, ['Average', $stats['average_marks'] . ' (' . $stats['average_percentage'] . '%)']);
                fputcsv($file, ['Pass Rate', $stats['pass_percentage'] . '%']);
                fputcsv($file, []);

                fputcsv($file, ['Rank', 'Roll No', 'Student Name', 'Marks Obtained', 'Percentage', 'Grade', 'Status', 'Remarks']);

                foreach ($rankedStudents as $row) {
                    fputcsv($file, [
                        $row['rank'],
                        $row['student']?->roll_number ?? '—',
                        $row['student']?->full_name ?? '—',
                        $row['marks'] !== null ? $row['marks'] : '—',
                        $row['percentage'] !== null ? $row['percentage'] . '%' : '—',
                        $row['grade'],
                        $row['status'] === 'present' ? ($row['passed'] ? 'Pass' : 'Fail') : ucfirst($row['status']),
                        $row['remarks'] ?? '',
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        // PDF Export
        $pdf = Pdf::loadView('teacher.quizzes.pdf.result', [
            'quiz' => $quiz->load(['schoolClass', 'section', 'subject', 'session', 'teacher']),
            'stats' => $stats,
            'rankedStudents' => $rankedStudents,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("{$filenameSafe}-results.pdf");
    }
}

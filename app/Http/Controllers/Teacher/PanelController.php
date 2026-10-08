<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TeacherAssignment;
use App\Services\TeacherPanelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PanelController extends Controller
{
    public function show(Request $request, TeacherPanelService $panel)
    {
        $page = $request->route('page');
        $teacher = $request->user()->teacher;
        $progressData = [];

        if ($page === 'progress' && $teacher) {
            $assignments = \App\Models\TeacherAssignment::where('teacher_id', $teacher->id)
                ->with(['schoolClass', 'section'])
                ->get();

            $classIds = $assignments->pluck('class_id')->unique();
            $sectionIds = $assignments->pluck('section_id')->unique();

            $students = \App\Models\Student::where('status', 'Active')
                ->whereIn('class_id', $classIds)
                ->whereIn('section_id', $sectionIds)
                ->orderBy('class_id')
                ->orderBy('section_id')
                ->orderByRaw('CAST(roll_number AS DECIMAL(12,2)) ASC')
                ->get();

            $selectedStudentId = (int) $request->get('student_id', $students->first()?->id);
            $selectedStudent = $students->firstWhere('id', $selectedStudentId) ?? $students->first();

            $results = collect();
            if ($selectedStudent) {
                $results = \App\Models\QuizResult::where('student_id', $selectedStudent->id)
                    ->whereHas('quiz', fn ($q) => $q->where('status', '!=', 'draft'))
                    ->with(['quiz.subject', 'quiz.schoolClass', 'quiz.section'])
                    ->get()
                    ->sortBy(fn ($r) => $r->quiz?->date?->timestamp ?? 0)
                    ->values();
            }

            $progressData = [
                'students' => $students,
                'selectedStudent' => $selectedStudent,
                'quizResults' => $results,
            ];
        }

        $timetableData = [];
        if ($page === 'timetable' && $teacher) {
            $session = \App\Models\AcademicSession::where('is_current', true)->first();
            if ($session) {
                $timetableData = app(\App\Services\TimetableService::class)->getTeacherMatrix($session->id, $teacher->id);
            }
        }

        return view('teacher.panel', array_merge([
            'title' => $request->route('title'),
            'page' => $page,
            'teacher' => $teacher,
            'notices' => $page === 'notices' ? $panel->notices() : collect(),
            'leaves' => $page === 'leaves' ? \App\Models\LeaveRequest::where('teacher_id', $teacher?->id ?? 0)->with('leaveType')->latest()->paginate(20) : collect(),
            'leaveCounts' => $page === 'leaves' ? \App\Models\LeaveRequest::where('teacher_id', $teacher?->id ?? 0)->selectRaw('LOWER(status) AS leave_status, COUNT(*) AS total')->groupByRaw('LOWER(status)')->pluck('total','leave_status') : collect(),
            'timetableData' => $timetableData,
        ], $progressData));
    }

    public function quiz(TeacherAssignment $assignment)
    {
        Gate::authorize('view', $assignment);
        return view('teacher.panel', ['title' => 'Quizzes & Results', 'page' => 'quizzes', 'assignment' => $assignment]);
    }

    public function progress(TeacherAssignment $assignment, TeacherPanelService $panel)
    {
        Gate::authorize('view', $assignment);
        return redirect()->route('teacher.attendance.register', $assignment);
    }
}

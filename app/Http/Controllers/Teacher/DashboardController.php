<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\TeacherPanelService;
use App\Services\AttendanceRecords;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TeacherPanelService $panel)
    {
        $papers = \App\Models\ExamSchedule::forTeacher($request->user())->with(['examSubject.exam.session','examSubject.subject','examSubject.schoolClass','section','invigilator.user'])
            ->orderBy('exam_date')->orderBy('start_time')->get();
        $pendingExamAssignments = \App\Models\ExamSubjectAssignment::awaitingPublicationForTeacher($request->user())
            ->with(['examSubject.exam','examSubject.subject','examSubject.schoolClass','section'])->get();
        return view('teacher.dashboard', $panel->dashboard($request->user()) + ['examPapers'=>$papers,'pendingExamAssignments'=>$pendingExamAssignments,
            'warnings' => app(AttendanceRecords::class)->warnings(today()->toDateString())]);
    }
}

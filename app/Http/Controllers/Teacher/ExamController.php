<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExamController extends Controller
{
    public function schedule(Request $request)
    {
        $papers = ExamSchedule::forTeacher($request->user())
            ->with(['examSubject.exam.session', 'examSubject.subject', 'examSubject.schoolClass', 'section', 'invigilator.user'])
            ->orderBy('exam_date')->orderBy('start_time')->paginate(15)->withQueryString();

        return view('teacher.exams.schedule', compact('papers'));
    }

    public function index(Request $request)
    {
        $filters=$request->validate(['status'=>['nullable',Rule::in(['not_started','draft','submitted','returned','approved'])]]);
        $query=ExamSchedule::forTeacher($request->user())->with(['examSubject.exam.session','examSubject.subject','examSubject.schoolClass','section','invigilator.user']);
        if ($request->filled('status')) $query->where('marks_status',$filters['status']);
        $papers=$query->orderBy('exam_date')->orderBy('start_time')->paginate(15)->withQueryString();
        $pendingExamAssignments = \App\Models\ExamSubjectAssignment::awaitingPublicationForTeacher($request->user())
            ->with(['examSubject.exam','examSubject.subject','examSubject.schoolClass','section'])->get();
        return view('teacher.exams.index',compact('papers','filters','pendingExamAssignments'));
    }
}

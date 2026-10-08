<?php

namespace App\Http\Controllers;

use App\Models\{Exam, Student};
use App\Services\ExamResults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Barryvdh\DomPDF\Facade\Pdf;

class ExamResultController extends Controller
{
    public function index(Request $request, Exam $exam, ExamResults $results)
    {
        Gate::authorize('view',$exam);
        $filters=$request->validate(['class_id'=>'nullable|integer|exists:school_classes,id','search'=>'nullable|string|max:100']);
        $normalizeSearch = fn ($value) => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)));
        $filters['search'] = preg_replace('/\s+/u', ' ', trim($filters['search'] ?? ''));
        $reports=collect($results->all($exam));
        if ($request->filled('class_id')) $reports=$reports->where('class_id',$filters['class_id']);
        if ($filters['search'] !== '') $reports=$reports->filter(fn($r)=>str_contains($normalizeSearch($r['student_name'].' '.$r['admission_no']),$normalizeSearch($filters['search'])));
        return view('exams.results.index',compact('exam','reports','filters'));
    }

    public function show(Request $request, Exam $exam, Student $student, ExamResults $results)
    {
        Gate::authorize('view',$exam);
        $request->validate(['download'=>'nullable|in:pdf']);
        $report=collect($results->all($exam))->first(fn($r)=>$r['student']->id===$student->id);
        abort_unless($report,404);
        $pdf=$request->input('download')==='pdf';
        $data=compact('exam','report','pdf');
        return $pdf ? Pdf::loadView('exams.results.report',$data)->setPaper('a4')->download('report-'.$exam->id.'-'.$student->id.'.pdf')
            : view('exams.results.report',$data);
    }
}

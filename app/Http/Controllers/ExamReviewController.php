<?php
namespace App\Http\Controllers;
use App\Models\{ExamSchedule, GradeScale};
use App\Services\{ExamSetup, ExamWorkflow};
use Illuminate\Http\Request;
class ExamReviewController extends Controller
{
    public function index()
    {
        return view('exams.approvals',['papers'=>ExamSchedule::whereIn('marks_status',['submitted','returned','approved'])
            ->with(['examSubject.exam','examSubject.subject','examSubject.schoolClass','section','teacher.user'])->latest('submitted_at')->paginate(20)]);
    }
    public function show(ExamSchedule $paper)
    {
        $paper->load(['examSubject.exam','examSubject.subject','examSubject.schoolClass','section','quiz.examRosterEntries','quiz.results']);
        return view('exams.review',['paper'=>$paper]);
    }
    public function action(Request $request, ExamSchedule $paper, ExamWorkflow $workflow)
    {
        $data=$request->validate(['action'=>'required|in:approve,return','reason'=>'nullable|string|max:1000']);
        $workflow->review($paper,$request->user(),$data['action'],$data['reason'] ?? null);
        return back()->with('success',$data['action']==='approve' ? 'Marks approved. These marks are included in the combined student report cards.' : 'Marks returned to the teacher for correction.');
    }
    public function deadline(Request $request, ExamSchedule $paper)
    {
        $data=$request->validate(['marks_deadline'=>'required|date','reason'=>'required|string|min:3|max:1000']);
        \Illuminate\Support\Facades\DB::transaction(function() use($request,$paper,$data) {
            $exam=\App\Models\Exam::whereKey($paper->examSubject->exam_id)->lockForUpdate()->firstOrFail();
            $paper=ExamSchedule::whereKey($paper->id)->lockForUpdate()->firstOrFail();
            if (!$exam->isCurrent() || $exam->status==='cancelled' || $exam->result_published_at) ExamSetup::fail('This examination deadline is read-only.');
            if (mb_strlen(trim($data['reason']))<3) ExamSetup::fail('Provide the deadline extension reason.');
            if (\Carbon\Carbon::parse($data['marks_deadline'])->lt(\Carbon\Carbon::parse($paper->exam_date->format('Y-m-d').' '.$paper->end_time))
                || ($paper->marks_deadline && \Carbon\Carbon::parse($data['marks_deadline'])->lte($paper->marks_deadline))) ExamSetup::fail('The extended deadline must be later than the current deadline and paper end time.');
            $old=$paper->marks_deadline?->toIso8601String();$paper->update(['marks_deadline'=>$data['marks_deadline']]);
            app(ExamSetup::class)->log($exam,$request->user(),'deadline_extended',['deadline'=>$old],['paper_id'=>$paper->id,'deadline'=>$data['marks_deadline']],$data['reason']);
        });
        return back()->with('success','Marks deadline updated.');
    }
}

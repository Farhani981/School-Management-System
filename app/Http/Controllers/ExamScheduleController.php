<?php
namespace App\Http\Controllers;
use App\Models\{Exam, Teacher};
use App\Services\{ExamDatesheet, ExamSetup};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
class ExamScheduleController extends Controller
{
    public function index(Request $request, Exam $exam, ExamDatesheet $datesheet)
    {
        Gate::authorize('view',$exam);
        if (!$exam->datesheet_published_at && !$request->has('export')) return redirect()->route('exams.show',$exam);
        $exam->load(['session','subjects.subject','subjects.schoolClass','subjects.assignments.teacher.user','participations.section','schedules']);
        $filters=$request->validate(['search'=>'nullable|string|max:150','per_page'=>['nullable',\Illuminate\Validation\Rule::in([10,25,50])],'paper'=>'nullable|integer','add'=>'nullable|boolean','export'=>['nullable',\Illuminate\Validation\Rule::in(['csv'])]]);
        $query=$exam->schedules()->with(['examSubject.subject','examSubject.schoolClass','section']);
        if ($request->filled('search')) $query->where(fn($q)=>$q->where('room','like','%'.$filters['search'].'%')->orWhereHas('examSubject.subject',fn($s)=>$s->where('name','like','%'.$filters['search'].'%'))->orWhereHas('examSubject.schoolClass',fn($s)=>$s->where('name','like','%'.$filters['search'].'%')));
        if ($request->input('export')==='csv') {
            $papers=$query->orderBy('exam_date')->get();
            return response()->streamDownload(function () use ($papers) {
                $file=fopen('php://output','w'); fputcsv($file,['Class','Section','Subject','Exam Date','Start Time','End Time','Room']);
                foreach($papers as $p) fputcsv($file,[$p->examSubject->schoolClass->name,$p->section->name,$p->examSubject->subject->name,$p->exam_date->format('Y-m-d'),$p->start_time,$p->end_time,$p->room]);
                fclose($file);
            },'exam-schedule.csv',['Content-Type'=>'text/csv']);
        }
        $editingPaper=$request->filled('paper') ? $exam->schedules()->findOrFail($filters['paper']) : null;
        return view('exams.schedule',['exam'=>$exam,'teachers'=>Teacher::where('status','Active')->with('user')->get(),
            'papers'=>$query->orderBy('exam_date')->orderBy('start_time')->paginate((int)($filters['per_page'] ?? 10))->withQueryString(),'filters'=>$filters,'editingPaper'=>$editingPaper,
            'checklist'=>$datesheet->checklist($exam),'setupChecklist'=>app(ExamSetup::class)->checklist($exam),
            'setupEditable'=>app(ExamSetup::class)->editable($exam),
            'editable'=>$exam->isCurrent() && !$exam->datesheet_published_at && in_array($exam->status,['draft','scheduled'])]);
    }
    public function store(Request $request, Exam $exam, ExamDatesheet $datesheet)
    {
        $request->validate(['action'=>'nullable|in:save,publish']);
        if ($request->has('slot')) {
            $data=$request->validate(['slot'=>['required','regex:/^[1-9][0-9]*:[1-9][0-9]*$/'],'exam_date'=>'required|date_format:Y-m-d','start_time'=>'required|date_format:H:i','end_time'=>'required|date_format:H:i','room'=>'required|string|max:100','invigilator_id'=>'required|integer','marks_deadline'=>'required|date','holiday_override'=>'nullable|boolean','reason'=>'nullable|string|max:1000']);
            $slot=$data['slot']; unset($data['slot']);
            $rows=[$slot=>$data];
        } else {
            $data=$request->validate(['rows'=>'required|array|max:300','rows.*'=>'array']);
            $rows=$data['rows'];
        }
        $publish=$request->input('action')==='publish';
        DB::transaction(function () use ($datesheet,$exam,$request,$rows,$publish) {
            $datesheet->save($exam,$request->user(),$rows);
            if ($publish) $datesheet->publish($exam->fresh(),$request->user());
        });
        return redirect()->route('exams.schedule.index',$exam)->with('success',$publish
            ? 'Datesheet published and assigned teachers notified. Papers are now available in My Examinations.'
            : 'Schedule saved, but not sent to teachers yet. Add any remaining papers, then Publish to Teachers.');
    }
    public function publish(Request $request, Exam $exam, ExamDatesheet $datesheet)
    {
        $datesheet->publish($exam,$request->user());
        return back()->with('success','Datesheet published. Assigned teachers can now see dates and enter marks.');
    }
}

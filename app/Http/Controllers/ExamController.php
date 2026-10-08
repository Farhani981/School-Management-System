<?php

namespace App\Http\Controllers;

use App\Models\{AcademicSession, Exam, SchoolClass, SchoolSection, Student, TeacherAssignment};
use App\Services\ExamSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Exam::class);
        $filters = $request->validate(['search'=>'nullable|string|max:150', 'session_id'=>'nullable|integer|exists:academic_sessions,id','per_page'=>['nullable',Rule::in([10,25,50])],'export'=>['nullable',Rule::in(['csv'])]]);
        $query = Exam::with(['session','classes','subjects.assignments','participations'])->withCount(['classes','subjects','participations','schedules']);
        if ($request->filled('search')) $query->where('name','like','%'.$filters['search'].'%');
        if ($request->filled('session_id')) $query->where('session_id',$filters['session_id']);
        if ($request->input('export')==='csv') {
            $rows=$query->orderByDesc('id')->get();
            return response()->streamDownload(function () use ($rows) {
                $file=fopen('php://output','w');
                fputcsv($file,['Exam Name','Exam Date','Start Time','End Time','Status']);
                foreach ($rows as $exam) fputcsv($file,[$exam->name,$exam->start_date->format('Y-m-d'),$exam->start_time,$exam->end_time,$exam->status_label]);
                fclose($file);
            },'exams.csv',['Content-Type'=>'text/csv']);
        }
        return view('exams.index', ['exams'=>$query->latest('id')->paginate((int)($filters['per_page'] ?? 10))->withQueryString(), 'sessions'=>AcademicSession::orderByDesc('id')->get(), 'filters'=>$filters]);
    }

    private function form(Exam $exam)
    {
        $sessionId = $exam->session_id ?? AcademicSession::where('is_current', true)->value('id');
        $pairs = Student::where('academic_session_id',$sessionId)->where('status','Active')->whereNotNull('class_id')->whereNotNull('section_id')->get(['class_id','section_id'])
            ->concat(TeacherAssignment::where('session_id',$sessionId)->get(['class_id','section_id']))
            ->concat($exam->exists ? $exam->participations()->get(['class_id','section_id']) : collect())->unique(fn($row)=>$row->class_id.':'.$row->section_id);
        return view('exams.form', ['exam'=>$exam, 'sessions'=>AcademicSession::where('is_current',true)->get(),
            'classes'=>SchoolClass::whereIn('id',$pairs->pluck('class_id'))->orderBy('id')->get(), 'sections'=>SchoolSection::whereIn('id',$pairs->pluck('section_id'))->get()->keyBy('id'), 'pairs'=>$pairs]);
    }

    public function create() { Gate::authorize('create', Exam::class); return $this->form(new Exam); }

    public function edit(Exam $exam, ExamSetup $setup)
    {
        Gate::authorize('update',$exam);
        if (!$setup->editable($exam)) ExamSetup::fail('This exam setup is read-only.');
        return $this->form($exam);
    }

    private function validated(Request $request): array
    {
        if (!$request->has('end_date')) $request->merge(['end_date'=>$request->input('start_date')]);
        return $request->validate(['name'=>'required|string|max:150', 'type'=>['required',Rule::in(array_keys(Exam::TYPES))],
            'session_id'=>'required|integer|exists:academic_sessions,id', 'start_date'=>'required|date_format:Y-m-d',
            'end_date'=>'required|date_format:Y-m-d|after_or_equal:start_date', 'description'=>'nullable|string|max:3000',
            'start_time'=>'nullable|date_format:H:i','end_time'=>'nullable|required_with:start_time|date_format:H:i|after:start_time',
            'participants'=>'required|array|min:1|max:300', 'participants.*'=>['required','string','distinct','regex:/^[1-9][0-9]*:[1-9][0-9]*$/']]);
    }

    public function store(Request $request, ExamSetup $setup)
    {
        Gate::authorize('create',Exam::class);
        $exam = $setup->save(null,$request->user(),$this->validated($request));
        return redirect()->route('exams.show',$exam)->with('success','Exam created. Now choose subjects, marks and teachers for each class.');
    }

    public function update(Request $request, Exam $exam, ExamSetup $setup)
    {
        $setup->save($exam,$request->user(),$this->validated($request));
        return redirect()->route('exams.show',$exam)->with('success','Exam details saved.');
    }

    public function show(Request $request, Exam $exam, ExamSetup $setup)
    {
        Gate::authorize('view',$exam);
        $request->validate(['class_id'=>'nullable|integer|exists:school_classes,id']);
        $exam->load(['session','classes.subjects','participations.section','subjects.subject','subjects.assignments.teacher.user','subjects.schedules']);
        $class = $exam->classes->firstWhere('id',(int)$request->input('class_id')) ?? $exam->classes->first();
        $assignments = TeacherAssignment::with('teacher.user')->where('session_id',$exam->session_id)
            ->where('class_id',$class?->id)->whereHas('teacher',fn($q)=>$q->where('status','Active'))->get();
        return view('exams.show',['exam'=>$exam,'selectedClass'=>$class,'assignments'=>$assignments,
            'teachers'=>\App\Models\Teacher::where('status','Active')->whereHas('user',fn($q)=>$q->role('Teacher'))->with('user')->get(),
            'editable'=>$setup->editable($exam),'checklist'=>$setup->checklist($exam)]);
    }

    public function datesheet(Request $request, Exam $exam, \App\Services\SimpleExamDatesheet $service)
    {
        $data=$request->validate(['classes'=>'required|array|max:100','classes.*'=>'array:selected,subjects,holiday_override,holiday_reason',
            'classes.*.selected'=>'nullable|array|max:100','classes.*.selected.*'=>'integer',
            'classes.*.subjects'=>'nullable|array|max:100','action'=>'required|in:save,publish']);
        $service->save($exam,$request->user(),$data['classes'],$data['action']==='publish');
        return redirect()->route('exams.show',$exam)->with('success',$data['action']==='publish'
            ? 'Datesheet sent to selected teachers. They can enter and submit student marks.'
            : 'Datesheet saved. You can edit it or send it to teachers.');
    }

    public function subjects(Request $request, Exam $exam, ExamSetup $setup)
    {
        $data = $request->validate(['class_id'=>'required|integer|exists:school_classes,id','selected'=>'nullable|array|max:100',
            'selected.*'=>'required|integer|distinct|exists:subjects,id','subjects'=>'nullable|array',
            'subjects.*'=>'array:total_marks,passing_marks,teachers','subjects.*.teachers'=>'nullable|array|max:300','subjects.*.teachers.*'=>'nullable|integer|exists:teachers,id']);
        $setup->subjects($exam,$request->user(),$data['class_id'],$data['selected'] ?? [],$data['subjects'] ?? []);
        $remaining = $setup->checklist($exam->fresh());
        if (!$remaining) {
            return redirect()->route('exams.schedule.index',$exam)->with('success','Subjects and assigned teachers saved. Now save paper dates and publish the datesheet.');
        }
        $nextClass = $exam->classes()->whereNotIn('school_classes.id',$exam->subjects()->select('class_id'))->first();
        return redirect()->route('exams.show',[$exam,'class_id'=>$nextClass?->id ?? $data['class_id']])->with('success','Subjects and assigned teachers saved. Complete the remaining class setups to continue to paper dates.');
    }

    public function cancel(Request $request, Exam $exam, ExamSetup $setup)
    {
        $data = $request->validate(['reason'=>'required|string|min:3|max:1000']);
        $setup->cancel($exam,$request->user(),trim($data['reason']));
        return redirect()->route('exams.show',$exam)->with('success','Exam cancelled. Its records have been preserved.');
    }

    public function loadTeachers(Request $request, Exam $exam, ExamSetup $setup)
    {
        $count = $setup->loadAssignedTeachers($exam,$request->user());
        return redirect()->route('exams.show',$exam)->with('success', $count.' subject setups loaded from existing teacher assignments. Classes without subject teachers still need assignments.');
    }
}

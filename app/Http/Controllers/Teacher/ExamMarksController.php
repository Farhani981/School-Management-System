<?php
namespace App\Http\Controllers\Teacher;
use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Services\ExamWorkflow;
use Illuminate\Http\Request;
class ExamMarksController extends Controller
{
    public function edit(Request $request, ExamSchedule $paper, ExamWorkflow $workflow)
    {
        $quiz=$workflow->prepare($paper,$request->user());
        $request->validate(['template'=>'nullable|in:csv']);
        if ($request->input('template')==='csv') {
            $roster=$quiz->examRosterEntries()->orderBy('id')->get();
            return response()->streamDownload(function () use ($roster) {
                $file=fopen('php://output','w');
                fputcsv($file,['admission_no','marks_obtained','theory_marks','practical_marks','attendance_status','public_remark']);
                foreach ($roster as $entry) fputcsv($file,[$entry->admission_no,'','','','present','']);
                fclose($file);
            },'exam-marks-template.csv',['Content-Type'=>'text/csv']);
        }
        $paper->refresh()->load(['examSubject.exam.session','examSubject.subject','examSubject.schoolClass','section']);
        return view('teacher.exams.marks',['paper'=>$paper,'quiz'=>$quiz,'roster'=>$quiz->examRosterEntries()->orderBy('id')->get(),
            'results'=>$quiz->results()->get()->keyBy('student_id'),'editable'=>$workflow->editable($paper)]);
    }
    public function save(Request $request, ExamSchedule $paper, ExamWorkflow $workflow)
    {
        $data=$request->validate(['rows'=>'required|array|max:1000','rows.*'=>'array:student_id,marks_obtained,theory_marks,practical_marks,attendance_status,public_remark',
            'rows.*.student_id'=>'required|integer','rows.*.attendance_status'=>'required|in:present,absent',
            'rows.*.marks_obtained'=>'nullable|numeric','rows.*.theory_marks'=>'nullable|numeric','rows.*.practical_marks'=>'nullable|numeric','rows.*.public_remark'=>'nullable|string|max:255','action'=>'required|in:draft,submit']);
        $workflow->save($paper,$request->user(),$data['rows'],$data['action']==='submit');
        return back()->with('success',$data['action']==='submit' ? 'Marks submitted for admin review. Editing is now locked.' : 'Draft saved. You can continue later.');
    }

    public function import(Request $request, ExamSchedule $paper, ExamWorkflow $workflow)
    {
        $request->validate(['file'=>'required|file|max:2048|extensions:csv']);
        $quiz=$workflow->prepare($paper,$request->user());
        $roster=$quiz->examRosterEntries()->get()->keyBy('admission_no');
        $file=fopen($request->file('file')->getRealPath(),'r');
        try {
            $headers=fgetcsv($file);
            if (!$headers) \App\Services\ExamSetup::fail('The CSV file is empty.');
            $headers=array_map(fn($h)=>trim($h),$headers);
            $headers[0]=ltrim($headers[0],"\xEF\xBB\xBF");
            if (count(array_unique($headers))!==count($headers) || !in_array('admission_no',$headers) || !in_array('attendance_status',$headers)) \App\Services\ExamSetup::fail('Use the downloaded CSV template.');
            $rows=[]; $seen=[];
            while (($values=fgetcsv($file))!==false) {
                if ($values===[null]) continue;
                if (count($values)!==count($headers)) \App\Services\ExamSetup::fail('A CSV row has the wrong number of columns.');
                $row=array_combine($headers,$values);
                $admission=trim($row['admission_no']);
                $entry=$roster->get($admission);
                if (!$entry || isset($seen[$admission])) \App\Services\ExamSetup::fail('Each admission number must belong to this paper and appear once.');
                if (count($rows)>=1000) \App\Services\ExamSetup::fail('Upload no more than 1,000 students per file.');
                $seen[$admission]=true;
                $rows[]=['student_id'=>$entry->student_id,'attendance_status'=>strtolower(trim($row['attendance_status'])),
                    'marks_obtained'=>trim($row['marks_obtained'] ?? ''),'theory_marks'=>trim($row['theory_marks'] ?? ''),
                    'practical_marks'=>trim($row['practical_marks'] ?? ''),'public_remark'=>trim($row['public_remark'] ?? '')];
            }
            if (count($rows)!==$roster->count()) \App\Services\ExamSetup::fail('Include every student in the downloaded template.');
            foreach ($rows as $row) if (mb_strlen($row['public_remark'])>255) \App\Services\ExamSetup::fail('Remarks may contain up to 255 characters.');
            $workflow->save($paper,$request->user(),$rows,true);
        } finally { fclose($file); }
        return redirect()->route('teacher.exams.marks.edit',$paper)->with('success','Marks uploaded and submitted to the administrator.');
    }
}

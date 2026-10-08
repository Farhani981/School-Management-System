<?php
namespace App\Services;

use App\Models\{Exam, ExamSchedule, Quiz, QuizResult, QuizResultAudit, Student, User};
use Illuminate\Support\Facades\DB;

class ExamWorkflow
{
    public function authorize(User $user, ExamSchedule $paper): void
    {
        abort_unless($user->hasRole('Teacher') && ExamSchedule::forTeacher($user)->whereKey($paper->id)->exists(),403);
    }

    public function editable(ExamSchedule $paper): bool
    {
        $exam=$paper->examSubject->exam;
        return $exam->isCurrent() && $exam->status!=='cancelled' && $exam->datesheet_published_at && !$exam->result_published_at
            && in_array($paper->marks_status,['not_started','draft','returned'])
            && (!$paper->marks_deadline || now()->lte($paper->marks_deadline) || $paper->allow_late_submission);
    }

    public function prepare(ExamSchedule $paper, User $user): Quiz
    {
        $this->authorize($user,$paper);
        return DB::transaction(function () use ($paper) {
            $exam=Exam::whereKey($paper->examSubject->exam_id)->lockForUpdate()->firstOrFail();
            $paper=ExamSchedule::with('examSubject.subject')->whereKey($paper->id)->lockForUpdate()->firstOrFail();
            $subject=$paper->examSubject;
            $quiz=Quiz::firstOrCreate(['exam_subject_id'=>$subject->id,'section_id'=>$paper->section_id],[
                'exam_schedule_id'=>$paper->id,'title'=>$exam->name.' / '.$subject->subject->name,'type'=>$exam->type,
                'class_id'=>$subject->class_id,'subject_id'=>$subject->subject_id,'teacher_id'=>$paper->teacher_id,
                'session_id'=>$exam->session_id,'date'=>$paper->exam_date,'total_marks'=>$subject->total_marks,'passing_marks'=>$subject->passing_marks,'status'=>'draft']);
            if ($quiz->class_id!=$subject->class_id || $quiz->session_id!=$exam->session_id || $quiz->subject_id!=$subject->subject_id
                || ($quiz->exam_schedule_id && $quiz->exam_schedule_id!=$paper->id)) ExamSetup::fail('The linked assessment does not match this exam paper. Contact the administrator.');
            $quiz->update(['exam_schedule_id'=>$paper->id]);
            if (!$paper->roster_frozen_at) {
                if ($exam->result_published_at) ExamSetup::fail('Published results cannot change their student list.');
                $source=Quiz::where('class_id',$subject->class_id)->where('section_id',$paper->section_id)->whereKeyNot($quiz->id)
                    ->whereHas('examSubject',fn($q)=>$q->where('exam_id',$exam->id))->whereHas('examSchedule',fn($q)=>$q->whereNotNull('roster_frozen_at'))->first();
                $students=$source ? $source->examRosterEntries()->get() : $quiz->rosterQuery()->get()->concat(Student::withTrashed()->whereIn('id',$quiz->results()->pluck('student_id'))->get())->unique('id');
                foreach ($students as $student) $quiz->examRosterEntries()->firstOrCreate(['student_id'=>$source ? $student->student_id : $student->id],
                    ['student_name'=>$source ? $student->student_name : $student->full_name,'admission_no'=>$student->admission_no,'roll_number'=>$student->roll_number]);
                if ($students->isNotEmpty()) $paper->update(['roster_frozen_at'=>now()]);
            }
            return $quiz;
        });
    }

    public function complete(Quiz $quiz): bool
    {
        $members=$quiz->examRosterEntries()->pluck('student_id');
        $results=$quiz->results()->get()->keyBy('student_id');
        return $members->isNotEmpty() && $members->every(function($id) use($results,$quiz) {
            $r=$results->get($id);
            return $r && ($r->attendance_status==='absent' && $r->marks_obtained===null || $r->attendance_status==='present' && $r->marks_obtained!==null && $r->marks_obtained>=0 && $r->marks_obtained<=$quiz->total_marks);
        });
    }

    public function save(ExamSchedule $paper, User $user, array $rows, bool $submit=false): void
    {
        $this->authorize($user,$paper);
        DB::transaction(function() use($paper,$user,$rows,$submit) {
            $exam=Exam::whereKey($paper->examSubject->exam_id)->lockForUpdate()->firstOrFail();
            $paper=ExamSchedule::with('examSubject.exam')->whereKey($paper->id)->lockForUpdate()->firstOrFail();
            $this->authorize($user,$paper);
            if (!$this->editable($paper)) ExamSetup::fail('Editing is locked or the deadline has passed. Ask the administrator to return the marks or extend the deadline.');
            $quiz=$this->prepare($paper,$user);
            $members=$quiz->examRosterEntries()->pluck('student_id')->all();$seen=[];
            foreach ($rows as $row) {
                $id=(int)($row['student_id'] ?? 0);
                if (!in_array($id,$members) || isset($seen[$id])) ExamSetup::fail('Each student must belong to this paper and appear only once.');
                $seen[$id]=true;$status=$row['attendance_status'] ?? '';
                if (!in_array($status,['present','absent'])) ExamSetup::fail('Select Present or Absent.');
                $marks=$status==='absent' ? null : ($row['marks_obtained'] ?? null);
                $theory=null; $practical=null; $partialComponents=false;
                if ($status==='present' && $paper->examSubject->has_practical) {
                    $theory=$row['theory_marks'] ?? null;
                    $practical=$row['practical_marks'] ?? null;
                    foreach (['theory_marks'=>$theory,'practical_marks'=>$practical] as $component=>$value) {
                        if ($value!==null && $value!=='' && (!is_numeric($value) || $value<0 || $value>$paper->examSubject->$component || round((float)$value,2)!=$value)) ExamSetup::fail('Enter '.$component.' within the configured maximum.');
                    }
                    $marks=($theory===null || $theory==='' || $practical===null || $practical==='') ? null : round((float)$theory+(float)$practical,2);
                    $theory=$theory==='' ? null : $theory;
                    $practical=$practical==='' ? null : $practical;
                    $partialComponents=$theory!==null || $practical!==null;
                } elseif ($status==='present') {
                    $theory=$marks;
                    $practical=0;
                }
                if ($marks==='' ) $marks=null;
                if ($marks!==null && (!is_numeric($marks) || $marks<0 || $marks>$quiz->total_marks || round((float)$marks,2)!=$marks)) ExamSetup::fail('Marks must be from 0 to '.$quiz->total_marks.' with at most two decimals.');
                $existing=$quiz->results()->where('student_id',$id)->first();
                $before=$existing?->only(['marks_obtained','attendance_status']) ?? [];
                if ($status==='present' && $marks===null && !$partialComponents) {
                    // An unfinished draft row remains missing, rather than becoming zero or Absent.
                    if ($existing) {
                        app(ExamSetup::class)->log($exam,$user,'draft_mark_cleared',['student_id'=>$id]+$before,['paper_id'=>$paper->id]);
                        $existing->delete();
                    }
                    continue;
                }
                $result=QuizResult::updateOrCreate(['quiz_id'=>$quiz->id,'student_id'=>$id],['marks_obtained'=>$marks,
                    'theory_marks'=>$status==='absent' ? null : $theory,'practical_marks'=>$status==='absent' ? null : $practical,
                    'public_remark'=>$row['public_remark'] ?? null,
                    'attendance_status'=>$status,'entered_by'=>$user->id,'entered_at'=>now(),'locked_at'=>null]);
                QuizResultAudit::create(['quiz_result_id'=>$result->id,'quiz_id'=>$quiz->id,'student_id'=>$id,
                    'old_marks'=>$before['marks_obtained'] ?? null,'new_marks'=>$marks,'old_attendance'=>$before['attendance_status'] ?? null,
                    'new_attendance'=>$status,'reason'=>$paper->return_reason ?? 'Draft marks saved','changed_by'=>$user->id,'created_at'=>now()]);
            }
            if ($submit && !$this->complete($quiz)) ExamSetup::fail('Enter marks or select Absent for every student before submitting.');
            $old=$paper->marks_status;
            $paper->update($submit ? ['marks_status'=>'submitted','submitted_at'=>now(),'submitted_by'=>$user->id,'reviewed_at'=>null,'reviewed_by'=>null] : ['marks_status'=>'draft']);
            if ($submit) $quiz->results()->update(['locked_at'=>now()]);
            app(ExamSetup::class)->log($exam,$user,$submit ? 'marks_submitted' : 'marks_draft_saved',['marks_status'=>$old],['paper_id'=>$paper->id,'marks_status'=>$paper->marks_status]);
            if ($submit) User::role(['Admin','Principal'])->each(fn($u)=>$u->notify(new \App\Notifications\ExamWorkflowUpdated([
                'message'=>$user->name.' submitted '.$exam->name.' marks. Student report cards are updated.','url'=>route('exams.results.index',$exam)])));
        });
    }

    public function review(ExamSchedule $paper, User $user, string $action, ?string $reason=null): void
    {
        abort_unless($user->hasAnyRole(['Admin','Principal']),403);
        DB::transaction(function() use($paper,$user,$action,$reason) {
            $exam=Exam::whereKey($paper->examSubject->exam_id)->lockForUpdate()->firstOrFail();
            $paper=ExamSchedule::whereKey($paper->id)->lockForUpdate()->firstOrFail();
            if (!$exam->isCurrent() || $exam->status==='cancelled' || $exam->result_published_at || $paper->marks_status!=='submitted') ExamSetup::fail('Only submitted marks in an active unpublished result can be reviewed.');
            if ((int)$paper->submitted_by===(int)$user->id) ExamSetup::fail('You cannot approve your own submission.');
            if (!in_array($action,['approve','return'])) ExamSetup::fail('Choose Approve or Return.');
            if ($action==='return' && mb_strlen(trim($reason ?? ''))<3) ExamSetup::fail('Give a reason for correction.');
            if (!$paper->quiz || !$this->complete($paper->quiz)) ExamSetup::fail('This paper has incomplete marks.');
            $paper->update(['marks_status'=>$action==='approve' ? 'approved' : 'returned','reviewed_by'=>$user->id,
                'reviewed_at'=>now(),'return_reason'=>$action==='return' ? trim($reason) : null]);
            $paper->quiz->results()->update(['locked_at'=>$action==='approve' ? now() : null]);
            app(ExamSetup::class)->log($exam,$user,'marks_'.$action,['marks_status'=>'submitted'],['paper_id'=>$paper->id,'marks_status'=>$paper->marks_status],$reason);
            $paper->teacher?->user?->notify(new \App\Notifications\ExamWorkflowUpdated([
                'message'=>$exam->name.' marks '.$paper->marks_status.($reason ? ': '.$reason : ''),'url'=>route('teacher.exams.marks.edit',$paper)]));
        });
    }
}

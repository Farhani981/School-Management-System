<?php

namespace App\Services;

use App\Models\{Exam, ExamSchedule, Holiday, Teacher, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Gate, Validator};

class ExamDatesheet
{
    public function save(Exam $exam, User $user, array $rows): void
    {
        Gate::forUser($user)->authorize('update',$exam);
        DB::transaction(function () use ($exam,$user,$rows) {
            $exam=Exam::orderBy('id')->lockForUpdate()->get()->firstWhere('id',$exam->id);
            if (!$exam->isCurrent() || $exam->datesheet_published_at || !in_array($exam->status,['draft','scheduled'])) ExamSetup::fail('Only an unpublished current-session datesheet can be edited.');
            if (app(ExamSetup::class)->checklist($exam)) ExamSetup::fail('Finish subject marks and teacher assignments before scheduling.');
            $saved=0;
            foreach ($rows as $key=>$row) {
                if (!preg_match('/^[1-9][0-9]*:[1-9][0-9]*$/',(string)$key)) ExamSetup::fail('Invalid scheduled subject/section.');
                if (empty($row['exam_date'])) continue;
                Validator::make($row,['exam_date'=>'required|date_format:Y-m-d','start_time'=>'required|date_format:H:i',
                    'end_time'=>'required|date_format:H:i|after:start_time','room'=>'nullable|string|max:100',
                    'invigilator_id'=>'nullable|integer|exists:teachers,id','marks_deadline'=>'required|date',
                    'holiday_override'=>'nullable|boolean','reason'=>'nullable|string|max:1000'])->validate();
                [$subjectId,$sectionId]=array_map('intval',explode(':',$key));
                $subject=$exam->subjects()->find($subjectId);
                $assignment=$subject?->assignments()->where('section_id',$sectionId)->first();
                if (!$assignment || !$exam->participations()->where('class_id',$subject->class_id)->where('section_id',$sectionId)->exists()) ExamSetup::fail('Choose a configured exam subject and participating section.');
                $date=Carbon::parse($row['exam_date']);
                if ($date->lt($exam->start_date) || $date->gt($exam->end_date)) ExamSetup::fail('Paper dates must fall within the examination start and end dates.');
                if (!empty($row['invigilator_id']) && !Teacher::whereKey($row['invigilator_id'])->where('status','Active')->exists()) ExamSetup::fail('Select an active invigilator.');
                if (Carbon::parse($row['marks_deadline'])->lt(Carbon::parse($row['exam_date'].' '.$row['end_time']))) ExamSetup::fail('Marks deadline must be after the paper ends.');
                $holiday=$date->isSunday() || Holiday::where('start_date','<=',$row['exam_date'])->where('end_date','>=',$row['exam_date'])->exists();
                if ($holiday && (empty($row['holiday_override']) || mb_strlen(trim($row['reason'] ?? ''))<3)) ExamSetup::fail('This date is a Sunday or school holiday. Choose another date, or provide an override reason.');
                $paper=ExamSchedule::firstOrNew(['exam_subject_id'=>$subjectId,'section_id'=>$sectionId]);
                $room=trim($row['room'] ?? '');
                $conflicts=ExamSchedule::whereDate('exam_date',$row['exam_date'])->where('start_time','<',$row['end_time'])->where('end_time','>',$row['start_time'])
                    ->when($paper->exists,fn($q)=>$q->whereKeyNot($paper->id))->whereHas('examSubject.exam',fn($q)=>$q->where('status','!=','cancelled'))->with('examSubject')->get();
                foreach ($conflicts as $other) {
                    if (((int)$other->examSubject->class_id===(int)$subject->class_id && (int)$other->section_id===$sectionId)
                        || ($room!=='' && mb_strtolower(trim($other->room ?? ''))===mb_strtolower($room)) || (!empty($row['invigilator_id']) && $other->invigilator_id==(int)$row['invigilator_id'])) {
                        ExamSetup::fail('Schedule conflict: class/section, room or invigilator already has a paper at this time.');
                    }
                }
                $old=$paper->getAttributes();
                $paper->fill(['exam_date'=>$row['exam_date'],'start_time'=>$row['start_time'],'end_time'=>$row['end_time'],
                    'room'=>$room ?: null,'teacher_id'=>$assignment->teacher_id,'invigilator_id'=>$row['invigilator_id'] ?? null,
                    'paper_deadline'=>$row['exam_date'],'marks_deadline'=>$row['marks_deadline'],'holiday_override'=>!empty($row['holiday_override'])]);
                $paper->save();$saved++;
                app(ExamSetup::class)->log($exam,$user,'schedule_saved',$old,['paper_id'=>$paper->id]+$paper->getAttributes(),$row['reason'] ?? null);
            }
            if (!$saved) ExamSetup::fail('Enter the date, times, room, invigilator and deadline for at least one paper.');
            $exam->update(['status'=>'scheduled']);
        });
    }

    public function checklist(Exam $exam): array
    {
        $errors=app(ExamSetup::class)->checklist($exam);
        foreach ($exam->subjects()->with('subject')->get() as $subject) {
            foreach ($exam->participations()->where('class_id',$subject->class_id)->with('section')->get() as $section) {
                $paper=$subject->schedules()->where('section_id',$section->section_id)->first();
                if (!$paper || !$paper->marks_deadline) $errors[]=$subject->subject->name.' / Section '.$section->section->name.': save the paper date and time.';
                elseif ($paper->invigilator_id && !Teacher::whereKey($paper->invigilator_id)->where('status','Active')->exists()) $errors[]=$subject->subject->name.': choose an active invigilator.';
            }
        }
        return array_unique($errors);
    }

    public function publish(Exam $exam, User $user): void
    {
        Gate::forUser($user)->authorize('update',$exam);
        DB::transaction(function () use ($exam,$user) {
            $exam=Exam::orderBy('id')->lockForUpdate()->get()->firstWhere('id',$exam->id);
            if (!$exam->isCurrent() || $exam->datesheet_published_at || !in_array($exam->status,['draft','scheduled'])) ExamSetup::fail('This datesheet cannot be published.');
            if ($errors=$this->checklist($exam)) ExamSetup::fail(implode(' ', $errors));
            // Revalidate all saved slots, including conflicts created since the previous save.
            $rows=[];
            foreach ($exam->schedules()->get() as $paper) $rows[$paper->exam_subject_id.':'.$paper->section_id]=[
                'exam_date'=>$paper->exam_date->format('Y-m-d'),'start_time'=>substr($paper->start_time,0,5),'end_time'=>substr($paper->end_time,0,5),
                'room'=>$paper->room,'invigilator_id'=>$paper->invigilator_id,'marks_deadline'=>$paper->marks_deadline->format('Y-m-d H:i'),
                'holiday_override'=>$paper->holiday_override,'reason'=>$paper->holiday_override ? 'Previously authorized holiday override' : null];
            $this->save($exam,$user,$rows);
            $exam->update(['datesheet_published_at'=>now(),'status'=>'schedule_published']);
            app(ExamSetup::class)->log($exam,$user,'datesheet_published',[],['published_at'=>now()->toIso8601String()]);
            User::whereHas('teacher',fn($q)=>$q->whereIn('id',$exam->schedules()->pluck('teacher_id')))->each(fn($u)=>$u->notify(new \App\Notifications\ExamWorkflowUpdated([
                'message'=>$exam->name.' schedule is published. Open My Exams to enter marks.','url'=>route('teacher.exams.index')])));
        });
    }
}

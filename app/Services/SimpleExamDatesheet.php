<?php

namespace App\Services;

use App\Models\{Exam, ExamSchedule, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Gate, Validator};

class SimpleExamDatesheet
{
    public function save(Exam $exam, User $user, array $classes, bool $publish): void
    {
        Gate::forUser($user)->authorize('update', $exam);
        DB::transaction(function () use ($exam,$user,$classes,$publish) {
            $exam=Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $setup=app(ExamSetup::class);
            if (!$setup->editable($exam)) ExamSetup::fail('This datesheet is locked after publication or marks entry.');
            $slots=[];
            foreach ($exam->classes as $class) {
                $data=$classes[$class->id] ?? [];
                $selected=array_values(array_unique(array_map('intval',$data['selected'] ?? [])));
                if (count($selected)!==count($data['selected'] ?? [])) ExamSetup::fail('Select each subject only once within '.$class->name.'.');
                if (!$selected) ExamSetup::fail('Select at least one subject for '.$class->name.'.');
                $rows=$data['subjects'] ?? [];
                foreach ($selected as $subjectId) {
                    $row=$rows[$subjectId] ?? [];
                    Validator::make($row,[
                        'theory_marks'=>'required|numeric|min:0|max:9999.99|decimal:0,2',
                        'practical_marks'=>'required|numeric|min:0|max:9999.99|decimal:0,2',
                        'passing_marks'=>'required|numeric|min:0|decimal:0,2',
                        'exam_date'=>'required|date_format:Y-m-d',
                        'start_time'=>'required|date_format:H:i','end_time'=>'required|date_format:H:i|after:start_time',
                        'teachers'=>'required|array',
                    ])->validate();
                    $row['total_marks']=round((float)$row['theory_marks']+(float)$row['practical_marks'],2);
                    if ($row['total_marks']<=0 || $row['total_marks']>9999.99 || $row['passing_marks']>$row['total_marks']) ExamSetup::fail('Check total and passing marks for '.$class->name.'.');
                    $rows[$subjectId]=$row;
                }
                $setup->subjects($exam,$user,$class->id,$selected,$rows);
                foreach ($exam->subjects()->where('class_id',$class->id)->get() as $subject) {
                    $row=$rows[$subject->subject_id];
                    $subject->update(['theory_marks'=>$row['theory_marks'],'practical_marks'=>$row['practical_marks'],'has_practical'=>$row['practical_marks']>0]);
                    foreach ($exam->participations()->where('class_id',$class->id)->get() as $section) {
                        $slots[$subject->id.':'.$section->section_id]=[
                            'exam_date'=>$row['exam_date'],'start_time'=>$row['start_time'],'end_time'=>$row['end_time'],
                            'room'=>null,'invigilator_id'=>null,
                            'marks_deadline'=>Carbon::parse($row['exam_date'].' '.$row['end_time'])->addDays(7)->format('Y-m-d H:i'),
                            'holiday_override'=>!empty($data['holiday_override']),'reason'=>$data['holiday_reason'] ?? null,
                        ];
                    }
                }
            }
            app(ExamDatesheet::class)->save($exam->fresh(),$user,$slots);
            if ($publish) app(ExamDatesheet::class)->publish($exam->fresh(),$user);
        });
    }
}

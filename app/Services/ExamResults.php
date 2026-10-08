<?php

namespace App\Services;

use App\Models\{Exam, GradeScale, Student};

class ExamResults
{
    public function all(Exam $exam): array
    {
        $exam->load(['session','schedules.examSubject.subject','schedules.examSubject.schoolClass','schedules.section','schedules.quiz.results','schedules.quiz.examRosterEntries','participations']);
        $groups=[];
        foreach ($exam->participations as $participation) {
            $papers=$exam->schedules->filter(fn($p)=>$p->examSubject->class_id==$participation->class_id && $p->section_id==$participation->section_id);
            $frozen=$papers->flatMap(fn($p)=>$p->quiz?->examRosterEntries ?? collect())->unique('student_id');
            $students=$frozen->isNotEmpty()
                ? Student::withTrashed()->whereIn('id',$frozen->pluck('student_id'))->with(['schoolClass','section'])->get()
                : Student::where('class_id',$participation->class_id)->where('section_id',$participation->section_id)->where('academic_session_id',$exam->session_id)->where('status','Active')->with(['schoolClass','section'])->get();
            $reports=[];
            foreach ($students as $student) {
                $rows=[]; $total=0; $maximum=0; $complete=$papers->isNotEmpty(); $passed=true;
                foreach ($papers as $paper) {
                    $result=$paper->quiz?->results->firstWhere('student_id',$student->id);
                    $submitted=in_array($paper->marks_status,['submitted','approved']);
                    $ready=$submitted && $result && ($result->attendance_status==='absent' || $result->marks_obtained!==null);
                    $complete=$complete && $ready;
                    $marks=$ready && $result->attendance_status==='present' ? (float)$result->marks_obtained : 0;
                    $maximum+=(float)$paper->examSubject->total_marks;
                    $total+=$marks;
                    $pass=$ready && $result->attendance_status==='present' && $marks>=(float)$paper->examSubject->passing_marks;
                    $passed=$passed && $pass;
                    $rows[]=['paper'=>$paper,'result'=>$ready ? $result : null,'ready'=>$ready,'pass'=>$pass,
                        'grade'=>$ready && $result->attendance_status==='present' ? GradeScale::forPercentage(round($marks/(float)$paper->examSubject->total_marks*100,2))?->grade : null];
                }
                $percentage=$maximum>0 ? round($total/$maximum*100,2) : 0;
                $snapshot=$frozen->firstWhere('student_id',$student->id);
                $reports[]=['student'=>$student,'student_name'=>$snapshot?->student_name ?? $student->full_name,
                    'class_name'=>$papers->first()?->examSubject->schoolClass->name ?? $student->schoolClass?->name,
                    'section_name'=>$papers->first()?->section->name ?? $student->section?->name,
                    'class_id'=>$participation->class_id,'section_id'=>$participation->section_id,
                    'admission_no'=>$snapshot?->admission_no ?? $student->admission_no,'roll_number'=>$snapshot?->roll_number ?? $student->roll_number,
                    'rows'=>$rows,'total'=>$total,'maximum'=>$maximum,'percentage'=>$percentage,'complete'=>$complete,
                    'grade'=>$complete ? GradeScale::forPercentage($percentage)?->grade : null,
                    'status'=>$complete ? ($passed ? 'PASS' : 'FAIL') : 'PENDING','rank'=>null,
                    'remarks'=>collect($rows)->pluck('result.public_remark')->filter()->unique()->join(' '),
                ];
            }
            $ranked=collect($reports)->where('complete',true)->sortByDesc('percentage')->values();
            foreach ($reports as &$report) {
                if ($report['complete']) $report['rank']=1+$ranked->where('percentage','>',$report['percentage'])->count();
            }
            unset($report);
            $groups=array_merge($groups,$reports);
        }
        return $groups;
    }
}

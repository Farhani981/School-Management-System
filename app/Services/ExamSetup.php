<?php

namespace App\Services;

use App\Models\{AcademicSession, Exam, ExamLog, ExamSubject, SchoolClass, Student, Teacher, TeacherAssignment, User};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;

class ExamSetup
{
    public static function fail(string $message, string $field = 'setup'): never
    {
        throw ValidationException::withMessages([$field=>$message]);
    }

    public function editable(Exam $exam): bool
    {
        return $exam->isCurrent() && in_array($exam->status,['draft','scheduled']) && !$exam->datesheet_published_at
            && !$exam->result_published_at
            && !DB::table('quizzes')->whereIn('exam_subject_id', $exam->subjects()->select('id'))->exists();
    }

    private function assertEditable(Exam $exam): void
    {
        if (!$this->editable($exam)) self::fail('This exam setup is read-only because scheduling or marks have started, the exam is cancelled, or its session is archived.');
    }

    public function log(Exam $exam, User $user, string $action, array $old, array $new, ?string $reason = null): void
    {
        ExamLog::create(['exam_id'=>$exam->id, 'exam_schedule_id'=>$new['paper_id'] ?? null, 'actor_id'=>$user->id, 'action'=>$action,
            'old_value'=>$old, 'new_value'=>$new, 'note'=>$reason, 'created_at'=>now()]);
    }

    public function save(?Exam $exam, User $user, array $data): Exam
    {
        Gate::forUser($user)->authorize($exam ? 'update' : 'create', $exam ?? Exam::class);
        return DB::transaction(function () use ($exam, $user, $data) {
            if ($exam) {
                $exam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
                $this->assertEditable($exam);
            }
            if (!AcademicSession::whereKey($data['session_id'])->where('is_current', true)->exists()) self::fail('Choose the current academic session.', 'session_id');
            if ($exam && $exam->subjects()->exists() && (int)$data['session_id'] !== (int)$exam->session_id) self::fail('The session cannot change after subjects have been configured.', 'session_id');
            $rows = [];
            foreach ($data['participants'] as $pair) {
                [$classId, $sectionId] = array_map('intval', explode(':', $pair));
                $enrolled = Student::where('class_id', $classId)->where('section_id', $sectionId)->where('academic_session_id', $data['session_id'])->where('status', 'Active')->exists();
                $assigned = TeacherAssignment::where('class_id', $classId)->where('section_id', $sectionId)->where('session_id', $data['session_id'])->exists();
                if (!$enrolled && !$assigned) self::fail('A selected class/section has no enrolment or teacher assignment in this session.', 'participants');
                $rows[] = ['class_id'=>$classId, 'section_id'=>$sectionId];
            }
            $before = $exam ? $exam->only(['name','session_id','start_date','end_date','description']) : [];
            $fields = collect($data)->except('participants')->all();
            if (!$exam) $exam = Exam::create($fields + ['status'=>'draft', 'created_by'=>$user->id]);
            else $exam->update($fields);
            // Removing a configured class requires explicit subject removal first.
            $classIds = array_values(array_unique(array_column($rows, 'class_id')));
            if ($exam->subjects()->whereNotIn('class_id', $classIds)->exists()) self::fail('Remove the subjects of a class before removing that class.', 'participants');
            $oldParticipation = $exam->participations()->get(['class_id','section_id'])->toArray();
            $exam->participations()->delete();
            $exam->participations()->createMany($rows);
            $exam->classes()->sync($classIds);
            // Removed sections lose their draft responsibility records.
            foreach ($exam->subjects()->get() as $subject) {
                $sections = collect($rows)->where('class_id', $subject->class_id)->pluck('section_id');
                $subject->assignments()->whereNotIn('section_id', $sections)->delete();
            }
            $this->log($exam, $user, $before ? 'exam_updated' : 'exam_created', $before + ['participations'=>$oldParticipation],
                $fields + ['participations'=>$rows]);
            return $exam;
        });
    }

    public function subjects(Exam $exam, User $user, int $classId, array $selected, array $rows): void
    {
        Gate::forUser($user)->authorize('update', $exam);
        DB::transaction(function () use ($exam, $user, $classId, $selected, $rows) {
            $exam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($exam);
            $sections = $exam->participations()->where('class_id', $classId)->pluck('section_id');
            if ($sections->isEmpty()) self::fail('Choose a participating class.');
            $allowed = SchoolClass::findOrFail($classId)->subjects()->where('status', 'Active')->pluck('subjects.id');
            if ($exam->subjects()->where('class_id',$classId)->whereNotIn('subject_id',$selected)->whereHas('schedules')->exists()) self::fail('A saved paper cannot be removed from this datesheet. Keep its subject selected, or cancel the exam and create a replacement.');
            $before = $exam->subjects()->where('class_id', $classId)->with('assignments')->get()->toArray();
            foreach ($selected as $id) {
                if (!$allowed->contains((int)$id)) self::fail('Only active subjects configured for this class can be used.');
                $row = $rows[$id] ?? [];
                if (!isset($row['total_marks'], $row['passing_marks']) || !is_numeric($row['total_marks']) || !is_numeric($row['passing_marks'])
                    || $row['total_marks'] <= 0 || $row['total_marks'] > 9999.99 || $row['passing_marks'] < 0 || $row['passing_marks'] > $row['total_marks']
                    || round((float)$row['total_marks'], 2) != $row['total_marks'] || round((float)$row['passing_marks'], 2) != $row['passing_marks']) {
                    self::fail('Enter valid total and passing marks. Passing marks cannot exceed the total; use at most two decimals.');
                }
                $teachers = [];
                foreach ($sections as $section) {
                    $teacherId = $row['teachers'][$section] ?? null;
                    $valid = Teacher::whereKey($teacherId)->where('status', 'Active')->whereHas('user',fn($q)=>$q->role('Teacher'))->exists();
                    if (!$valid) self::fail('Select an active teacher with a teacher portal account for each section.');
                    $teachers[] = ['section_id'=>$section, 'teacher_id'=>$teacherId];
                }
                $subject = ExamSubject::updateOrCreate(['exam_id'=>$exam->id, 'class_id'=>$classId, 'subject_id'=>$id],
                    ['total_marks'=>$row['total_marks'], 'passing_marks'=>$row['passing_marks'], 'theory_marks'=>$row['total_marks'], 'practical_marks'=>0, 'has_practical'=>false]);
                $subject->assignments()->delete();
                $subject->assignments()->createMany($teachers);
            }
            $exam->subjects()->where('class_id', $classId)->whereNotIn('subject_id', $selected)->delete();
            $this->log($exam, $user, 'exam_subjects_saved', ['subjects'=>$before],
                ['class_id'=>$classId, 'subjects'=>$exam->subjects()->where('class_id', $classId)->with('assignments')->get()->toArray()]);
        });
    }

    public function checklist(Exam $exam): array
    {
        $errors = [];
        $exam->loadMissing(['classes','participations.section','subjects.subject','subjects.assignments']);
        if ($exam->participations->isEmpty()) $errors[] = 'Select participating sections.';
        foreach ($exam->classes as $class) {
            $subjects = $exam->subjects->where('class_id', $class->id);
            if ($subjects->isEmpty()) $errors[] = $class->name.': select subjects and marks.';
            foreach ($subjects as $subject) {
                if ($subject->total_marks <= 0 || $subject->passing_marks < 0 || $subject->passing_marks > $subject->total_marks) $errors[] = $class->name.' / '.$subject->subject?->name.': correct the total and passing marks.';
                if ($subject->subject?->status !== 'Active' || !$class->subjects()->whereKey($subject->subject_id)->exists()) $errors[] = $class->name.' / '.$subject->subject?->name.': subject is no longer active or assigned to this class.';
                foreach ($exam->participations->where('class_id', $class->id) as $participation) {
                    $teacherId = $subject->assignments->firstWhere('section_id', $participation->section_id)?->teacher_id;
                    if (!Teacher::whereKey($teacherId)->where('status','Active')->whereHas('user',fn($q)=>$q->role('Teacher'))->exists()) {
                        $errors[] = $class->name.' / '.$participation->section->name.' / '.$subject->subject?->name.': confirm the assigned teacher.';
                    }
                }
            }
        }
        return $errors;
    }

    public function loadAssignedTeachers(Exam $exam, User $user): int
    {
        Gate::forUser($user)->authorize('update', $exam);
        return DB::transaction(function () use ($exam,$user) {
            $exam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($exam);
            $count = 0;
            foreach ($exam->classes as $class) {
                $sections = $exam->participations()->where('class_id',$class->id)->pluck('section_id');
                $existing = $exam->subjects()->where('class_id',$class->id)->with('assignments')->get();
                $selected = $existing->pluck('subject_id')->all();
                $rows = [];
                $repair = false;
                foreach ($existing as $subject) {
                    $teachers = $subject->assignments->pluck('teacher_id','section_id')->all();
                    foreach ($sections as $section) {
                        $eligible = TeacherAssignment::where('class_id',$class->id)->where('section_id',$section)
                            ->where('subject_id',$subject->subject_id)->where('session_id',$exam->session_id)
                            ->whereHas('teacher',fn($q)=>$q->where('status','Active'))->get()->unique('teacher_id');
                        if ($eligible->count()===1 && !in_array($teachers[$section] ?? null,$eligible->pluck('teacher_id')->all())) {
                            $teachers[$section]=$eligible->first()->teacher_id;
                            $repair=true;
                        }
                    }
                    $rows[$subject->subject_id] = ['total_marks'=>$subject->total_marks,'passing_marks'=>$subject->passing_marks,'teachers'=>$teachers];
                }
                foreach ($class->subjects()->where('status','Active')->get() as $subject) {
                    if (in_array($subject->id,$selected)) continue;
                    $teachers = [];
                    foreach ($sections as $section) {
                        $eligible = TeacherAssignment::where('class_id',$class->id)->where('section_id',$section)->where('subject_id',$subject->id)->where('session_id',$exam->session_id)
                            ->whereHas('teacher',fn($q)=>$q->where('status','Active'))->get()->unique('teacher_id');
                        if ($eligible->count() !== 1) continue 2;
                        $teachers[$section] = $eligible->first()->teacher_id;
                    }
                    $selected[] = $subject->id;
                    $rows[$subject->id] = ['total_marks'=>$subject->pivot->total_marks ?? 100,'passing_marks'=>$subject->pivot->pass_marks ?? 40,'teachers'=>$teachers];
                    $count++;
                }
                if (count($selected)>$existing->count() || $repair) $this->subjects($exam,$user,$class->id,$selected,$rows);
            }
            return $count;
        });
    }

    public function cancel(Exam $exam, User $user, string $reason): void
    {
        Gate::forUser($user)->authorize('cancel', $exam);
        if (mb_strlen(trim($reason)) < 3) self::fail('Provide a reason for cancellation.', 'reason');
        DB::transaction(function () use ($exam, $user, $reason) {
            $exam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            if (!$exam->isCurrent() || $exam->result_published_at || $exam->status === 'cancelled') self::fail('Only an active current-session exam without published results can be cancelled.');
            $before = $exam->status;
            $exam->update(['status'=>'cancelled']);
            $this->log($exam, $user, 'exam_cancelled', ['status'=>$before], ['status'=>'cancelled'], $reason);
        });
    }
}

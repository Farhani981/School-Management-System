<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    protected $guarded = ['id'];

    // Store calendar dates without a time component on every database driver.
    public function setExamDateAttribute($value): void
    {
        $this->attributes['exam_date'] = $value === null ? null : Carbon::parse($value)->toDateString();
    }

    public function setPaperDeadlineAttribute($value): void
    {
        $this->attributes['paper_deadline'] = $value === null ? null : Carbon::parse($value)->toDateString();
    }

    protected function casts(): array
    {
        return ['exam_date' => 'date', 'paper_deadline' => 'date', 'holiday_override' => 'boolean', 'marks_deadline' => 'datetime', 'allow_late_submission' => 'boolean', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'roster_frozen_at' => 'datetime'];
    }

    public function examSubject() { return $this->belongsTo(ExamSubject::class); }
    public function section() { return $this->belongsTo(SchoolSection::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function invigilator() { return $this->belongsTo(Teacher::class, 'invigilator_id'); }
    public function quiz() { return $this->hasOne(Quiz::class, 'exam_schedule_id'); }
    public function submittedBy() { return $this->belongsTo(User::class, 'submitted_by'); }
    public function reviewedBy() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function logs() { return $this->hasMany(ExamLog::class); }

    public function scopeForTeacher(Builder $query, User $user): void
    {
        $teacher = $user->teacher;
        $id = $teacher?->status === 'Active' ? $teacher->id : 0;
        $query->where('teacher_id', $id)->whereHas('examSubject.exam', fn ($q) => $q->whereNotNull('datesheet_published_at')->where('status', '!=', 'cancelled'))
            ->whereExists(function ($q) use ($id) {
                $q->selectRaw('1')->from('exam_subject_assignments as ownership')
                    ->join('exam_subjects as owned_subject', 'owned_subject.id', '=', 'exam_schedules.exam_subject_id')
                    ->join('exams as owned_exam', 'owned_exam.id', '=', 'owned_subject.exam_id')
                    ->where('ownership.teacher_id', $id)->whereColumn('ownership.exam_subject_id', 'owned_subject.id')
                    ->whereColumn('ownership.section_id', 'exam_schedules.section_id');
            });
    }

    public function getTimeLabelAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('g:i A').' - '.Carbon::parse($this->end_time)->format('g:i A');
    }

    public function getWorkflowStatusAttribute(): string
    {
        if ($this->examSubject->exam->result_published_at) return 'Published';
        if ($this->marks_status === 'approved') return 'Approved';
        if ($this->marks_status === 'returned') return 'Returned';
        if ($this->marks_status === 'submitted') return 'Marks submitted';
        if ($this->marks_status === 'draft') return 'Draft';
        if (now()->gte(Carbon::parse($this->exam_date->toDateString().' '.$this->end_time))) return 'Marks Pending';
        return $this->paper_status === 'uploaded' ? 'Paper uploaded' : 'Scheduled';
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->workflow_status) {
            'Published', 'Approved', 'Paper uploaded' => 'done',
            'Returned' => 'problem', default => 'pending',
        };
    }
}

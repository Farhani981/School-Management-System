<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    public const TYPES = [
        'quiz' => 'Quiz',
        'test' => 'Class Test',
        'monthly' => 'Monthly Test',
        'midterm' => 'Midterm Exam',
        'final' => 'Final Exam',
        'assignment' => 'Assignment',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'published' => 'Published',
        'closed' => 'Closed',
    ];

    protected $fillable = [
        'exam_subject_id',
        'exam_schedule_id',
        'title',
        'type',
        'class_id',
        'section_id',
        'subject_id',
        'teacher_id',
        'session_id',
        'date',
        'total_marks',
        'passing_marks',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_marks' => 'decimal:2',
            'passing_marks' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }

    public function examSchedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class);
    }

    public function examRosterEntries(): HasMany
    {
        return $this->hasMany(ExamRosterEntry::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class, 'section_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id')->withTrashed();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(QuizResult::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(QuizResultAudit::class)->latest('created_at');
    }

    /**
     * Active students currently enrolled in this quiz's class, section and session.
     */
    public function rosterQuery()
    {
        return Student::where('status', 'Active')
            ->where('class_id', $this->class_id)
            ->where('section_id', $this->section_id)
            ->where('academic_session_id', $this->session_id)
            ->orderByRaw('CAST(roll_number AS DECIMAL(12, 2)) ASC')
            ->orderBy('roll_number', 'ASC')
            ->orderBy('id', 'ASC');
    }

    /**
     * Whether any marks or attendance entries have been saved for this quiz.
     */
    public function hasMarksEntered(): bool
    {
        return $this->results()
            ->where(function ($q) {
                $q->whereNotNull('marks_obtained')
                    ->orWhereIn('attendance_status', ['absent', 'exempt']);
            })
            ->exists();
    }

    /**
     * Count of students who have an entered mark or are marked absent/exempt.
     */
    public function enteredCount(): int
    {
        return $this->results()
            ->where(function ($q) {
                $q->whereNotNull('marks_obtained')
                    ->orWhereIn('attendance_status', ['absent', 'exempt']);
            })
            ->count();
    }

    /**
     * Total eligible active students in this quiz's class/section.
     */
    public function activeStudentsCount(): int
    {
        return $this->rosterQuery()->count();
    }

    /**
     * Computed status according to user specification:
     * Draft, Marks pending (published but some students have no entry), Completed (all entered), Closed.
     */
    public function computedStatus(): string
    {
        if ($this->status === 'draft') {
            return 'draft';
        }

        if ($this->status === 'closed') {
            return 'closed';
        }

        $totalActive = $this->activeStudentsCount();
        $entered = $this->enteredCount();

        if ($totalActive > 0 && $entered >= $totalActive) {
            return 'completed';
        }

        return 'pending';
    }

    public function computedStatusLabel(): string
    {
        return match ($this->computedStatus()) {
            'draft' => 'Draft',
            'closed' => 'Closed',
            'completed' => 'Completed',
            'pending' => 'Marks pending',
            default => 'Published',
        };
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}

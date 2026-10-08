<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizResult extends Model
{
    public const ATTENDANCE_STATUSES = [
        'present' => 'Present',
        'absent' => 'Absent',
        'exempt' => 'Exempt',
    ];

    protected $fillable = [
        'theory_marks', 'practical_marks', 'locked_at',
        'quiz_id',
        'student_id',
        'marks_obtained',
        'attendance_status',
        'remarks',
        'public_remark',
        'entered_by',
        'entered_at',
    ];

    protected function casts(): array
    {
        return [
            'marks_obtained' => 'decimal:2',
            'entered_at' => 'datetime',
            'theory_marks' => 'decimal:2', 'practical_marks' => 'decimal:2', 'locked_at' => 'datetime',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(QuizResultAudit::class)->latest('created_at');
    }

    /**
     * Compute percentage at runtime based on the parent quiz's total marks.
     */
    public function getPercentageAttribute(): ?float
    {
        if ($this->marks_obtained === null || ! $this->quiz || $this->quiz->total_marks <= 0) {
            return null;
        }

        return round(((float) $this->marks_obtained / (float) $this->quiz->total_marks) * 100, 2);
    }

    /**
     * Pass / Fail status.
     * Note: Absent and Exempt students are not counted as passed/failed.
     */
    public function getIsPassedAttribute(): ?bool
    {
        if ($this->attendance_status !== 'present' || $this->marks_obtained === null || ! $this->quiz) {
            return null;
        }

        return (float) $this->marks_obtained >= (float) $this->quiz->passing_marks;
    }

    /**
     * Dynamically compute grade from the grade_scales table at display time.
     */
    public function getGradeScaleAttribute(): ?GradeScale
    {
        if ($this->attendance_status !== 'present' || $this->percentage === null) {
            return null;
        }

        return GradeScale::forPercentage($this->percentage);
    }

    public function getGradeAttribute(): string
    {
        if ($this->attendance_status === 'absent') {
            return 'ABS';
        }
        if ($this->attendance_status === 'exempt') {
            return 'EXM';
        }

        return $this->grade_scale?->grade ?? '—';
    }
}

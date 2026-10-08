<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentNote extends Model
{
    public const TAGS = [
        'Needs support',
        'Gifted',
        'Behavior',
        'Parent contact',
        'Other',
    ];

    protected $fillable = [
        'student_id',
        'teacher_id',
        'session_id',
        'note',
        'tag',
        'follow_up_date',
        'is_done',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_date' => 'date',
            'is_done' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    public function isOverdue(): bool
    {
        return ! $this->is_done && $this->follow_up_date && $this->follow_up_date->isPast();
    }
}

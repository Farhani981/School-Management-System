<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizResultAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'quiz_result_id',
        'quiz_id',
        'student_id',
        'old_marks',
        'new_marks',
        'old_attendance',
        'new_attendance',
        'reason',
        'changed_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_marks' => 'decimal:2',
            'new_marks' => 'decimal:2',
            'created_at' => 'datetime',
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

    public function result(): BelongsTo
    {
        return $this->belongsTo(QuizResult::class, 'quiz_result_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['exam_id', 'exam_schedule_id', 'action', 'actor_id', 'note', 'old_value', 'new_value', 'created_at'];

    protected function casts(): array
    {
        return ['old_value' => 'array', 'new_value' => 'array', 'created_at' => 'datetime'];
    }

    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
    public function schedule(): BelongsTo { return $this->belongsTo(ExamSchedule::class, 'exam_schedule_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSubjectAssignment extends Model
{
    public $timestamps = false;
    protected $fillable = ['exam_subject_id', 'section_id', 'teacher_id'];
    public function examSubject() { return $this->belongsTo(ExamSubject::class); }
    public function section() { return $this->belongsTo(SchoolSection::class, 'section_id'); }
    public function teacher() { return $this->belongsTo(Teacher::class)->withTrashed(); }

    public function scopeAwaitingPublicationForTeacher(\Illuminate\Database\Eloquent\Builder $query, User $user): void
    {
        $id = $user->teacher?->status === 'Active' ? $user->teacher->id : 0;
        $query->where('teacher_id', $id)
            ->whereHas('examSubject.exam', fn ($q) => $q->whereNull('datesheet_published_at')->where('status', '!=', 'cancelled')->whereHas('session', fn ($s) => $s->where('is_current', true)));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    public const TYPES = ['monthly'=>'Monthly Test','midterm'=>'Mid Term','final'=>'Final Term','annual'=>'Annual Exam','first_term'=>'First Term','second_term'=>'Second Term','other'=>'Custom'];

    public const STATUSES = ['draft'=>'Draft','scheduled'=>'Scheduled','schedule_published'=>'Schedule Published','ongoing'=>'Ongoing','completed'=>'Completed','result_pending'=>'Result Pending','result_published'=>'Result Published','cancelled'=>'Cancelled'];

    protected $fillable = ['name', 'type', 'session_id', 'term', 'start_date', 'end_date', 'start_time', 'end_time', 'weightage', 'status', 'created_by', 'datesheet_published_at', 'description', 'result_published_at', 'result_published_by', 'result_settings_snapshot', 'result_snapshot','result_publication_version'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'weightage' => 'decimal:2', 'datesheet_published_at' => 'datetime', 'result_published_at' => 'datetime', 'result_settings_snapshot' => 'array', 'result_snapshot'=>'array','result_publication_version' => 'integer'];
    }

    public function session()
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'exam_classes', 'exam_id', 'class_id');
    }

    public function subjects()
    {
        return $this->hasMany(ExamSubject::class);
    }

    public function participations() { return $this->hasMany(ExamParticipation::class); }

    public function getLifecycleStatusAttribute(): string
    {
        if ($this->status === 'cancelled') return 'cancelled';
        if ($this->result_published_at) return 'result_published';
        if ($this->datesheet_published_at) {
            if (today()->lt($this->start_date)) return 'schedule_published';
            if (today()->lte($this->end_date)) return 'ongoing';
            return 'result_pending';
        }
        return match ($this->status) {
            'marks_entry' => 'result_pending', 'published' => 'completed',
            default => $this->status,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->lifecycle_status] ?? 'Archived';
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schedules()
    {
        return $this->hasManyThrough(ExamSchedule::class, ExamSubject::class);
    }

    public function logs()
    {
        return $this->hasMany(ExamLog::class);
    }

    public function resultPublisher()
    {
        return $this->belongsTo(User::class, 'result_published_by');
    }

    public function isCurrent(): bool
    {
        return (bool) $this->session?->is_current;
    }
}

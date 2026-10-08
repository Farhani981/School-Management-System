<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendance extends Model
{
    protected $fillable = [
        'student_id',
        'attendance_date',
        'status',
        'remarks',
        'date', 'academic_session_id', 'school_class_id', 'section_id', 'marked_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'date' => 'date:Y-m-d',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public const STATUSES = ['present', 'absent', 'late', 'leave', 'half_day'];

    public function setDateAttribute($value): void
    {
        $this->attributes['date'] = $value ? Carbon::parse($value)->toDateString() : null;
    }

    public function setAttendanceDateAttribute($value): void
    {
        $this->attributes['attendance_date'] = $value ? Carbon::parse($value)->toDateString() : null;
    }

    protected static function booted(): void
    {
        static::saving(function ($record) {
            $date = $record->isDirty('date') ? $record->date : ($record->attendance_date ?? $record->date);
            $record->date = $date;
            $record->attendance_date = $date;
            $record->status = strtolower(str_replace(' ', '_', $record->status));
        });
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section()
    {
        return $this->belongsTo(SchoolSection::class, 'section_id');
    }

    public function session()
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function marker()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}

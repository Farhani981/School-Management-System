<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryMember extends Model
{
    public const TYPES = ['Student', 'Teacher'];
    public const STATUSES = ['Active', 'Inactive', 'Suspended'];

    protected $fillable = ['member_type', 'student_id', 'teacher_id', 'registration_date', 'borrowing_limit', 'status'];

    protected function casts(): array
    {
        return ['registration_date' => 'date', 'borrowing_limit' => 'integer'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function transactions()
    {
        return $this->hasMany(LibraryTransaction::class, 'member_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }

    public function getPersonAttribute()
    {
        return $this->member_type === 'Student' ? $this->student : $this->teacher;
    }

    public function getNameAttribute(): string
    {
        return $this->member_type === 'Student' ? ($this->student?->full_name ?? 'Student unavailable') : ($this->teacher?->name ?? 'Teacher unavailable');
    }

    public function getReferenceAttribute(): ?string
    {
        return $this->member_type === 'Student' ? $this->student?->admission_no : $this->teacher?->employee_id;
    }

    public function getContextAttribute(): string
    {
        if ($this->member_type === 'Teacher') {
            return $this->teacher?->department?->name ?? 'Not set';
        }

        return collect([$this->student?->schoolClass?->name, $this->student?->section?->name])->filter()->implode(' / ') ?: 'Not set';
    }

    public function scopeSearch($query, string $search): void
    {
        $term = '%'.$search.'%';
        $query->where(fn ($q) => $q->where('member_id', 'like', $term)
            ->orWhereHas('student', fn ($s) => $s->where(function ($name) use ($search) {
                foreach (preg_split('/\s+/u', trim($search)) as $word) {
                    $name->where(fn ($part) => $part->where('first_name', 'like', '%'.$word.'%')->orWhere('last_name', 'like', '%'.$word.'%'));
                }
            })->orWhere('admission_no', 'like', $term)->orWhere('phone', 'like', $term))
            ->orWhereHas('teacher', fn ($t) => $t->where('name', 'like', $term)->orWhere('employee_id', 'like', $term)->orWhere('phone', 'like', $term)));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Subject extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'type', 'category', 'description', 'status'];

    protected static function booted(): void
    {
        // Name-only teacher setup remains compatible; full forms accept custom codes.
        static::creating(function (Subject $subject) {
            $subject->code ??= 'SUB-'.Str::upper(Str::random(10));
        });
    }

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subject')->withPivot(['total_marks', 'pass_marks', 'practical_marks', 'is_compulsory', 'display_order'])->withTimestamps();
    }

    public function marks()
    {
        return $this->hasMany(SubjectMark::class);
    }

    public function teacherAssignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    protected $guarded = [];

    public function feeStructures()
    {
        return $this->hasMany(FeeStructure::class);
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'class_subject')->withPivot(['total_marks', 'pass_marks', 'practical_marks', 'is_compulsory', 'display_order'])->withTimestamps()->orderByPivot('display_order');
    }

    public function teacherAssignments()
    {
        return $this->hasMany(TeacherAssignment::class, 'class_id');
    }
}

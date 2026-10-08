<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicSession extends Model
{
    protected $guarded = [];

    public function feeStructures()
    {
        return $this->hasMany(FeeStructure::class);
    }

    public function teacherAssignments()
    {
        return $this->hasMany(TeacherAssignment::class, 'session_id');
    }
}

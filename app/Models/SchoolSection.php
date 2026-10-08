<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolSection extends Model
{
    protected $guarded = [];

    public function teacherAssignments()
    {
        return $this->hasMany(TeacherAssignment::class, 'section_id');
    }
}

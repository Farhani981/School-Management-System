<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherAttendance extends Model
{
    protected $guarded = [];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAttendance extends Model
{
    protected $fillable = ['teacher_id', 'date', 'status', 'check_in', 'check_out', 'late_minutes', 'remarks'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'late_minutes' => 'integer'];
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }
}

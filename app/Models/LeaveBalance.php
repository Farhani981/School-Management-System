<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    protected $fillable = ['teacher_id', 'leave_type_id', 'session_id', 'total', 'used'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'used' => 'decimal:2'];
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function session()
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }
}

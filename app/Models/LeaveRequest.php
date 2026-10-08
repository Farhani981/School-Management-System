<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'reviewed_at' => 'datetime', 'from_date' => 'date', 'to_date' => 'date', 'days' => 'decimal:2', 'decided_at' => 'datetime', 'cancel_requested' => 'boolean', 'added_by_admin' => 'boolean'];
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }

    public function logs()
    {
        return $this->hasMany(LeaveRequestLog::class, 'request_id');
    }

    public function session()
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}

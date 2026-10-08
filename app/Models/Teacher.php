<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    public function staffAttendances()
    {
        return $this->hasMany(StaffAttendance::class);
    }

    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['joining_date' => 'date', 'date_of_birth' => 'date'];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasMany(TeacherDocument::class);
    }

    public function assignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function leaves()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function salaryStructure()
    {
        return $this->hasOne(SalaryStructure::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function attendances()
    {
        return $this->hasMany(TeacherAttendance::class);
    }

    public function advances()
    {
        return $this->hasMany(TeacherAdvance::class);
    }

    public function getInitialsAttribute()
    {
        return collect(preg_split('/\s+/', trim($this->name)))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    }
}

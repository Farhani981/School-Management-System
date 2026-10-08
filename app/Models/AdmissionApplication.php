<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionApplication extends Model
{
    protected $fillable = ['applicant_name', 'father_name', 'phone', 'applying_for_class_id', 'previous_school', 'notes', 'status', 'reviewed_by', 'reviewed_at', 'student_id'];
    protected $casts = ['reviewed_at' => 'datetime'];
    public function student() { return $this->belongsTo(Student::class)->withTrashed(); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}

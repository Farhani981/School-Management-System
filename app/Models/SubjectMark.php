<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectMark extends Model
{
    protected $fillable = ['subject_id', 'school_class_id', 'student_id', 'assessment', 'marks'];

    protected function casts(): array
    {
        return ['marks' => 'decimal:2'];
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }
}

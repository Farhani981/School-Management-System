<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSubject extends Model
{
    protected $fillable = ['exam_id', 'class_id', 'subject_id', 'total_marks', 'passing_marks', 'has_practical', 'theory_marks', 'practical_marks'];

    protected function casts(): array
    {
        return ['total_marks' => 'decimal:2', 'passing_marks' => 'decimal:2', 'theory_marks' => 'decimal:2', 'practical_marks' => 'decimal:2', 'has_practical' => 'boolean'];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function schedules()
    {
        return $this->hasMany(ExamSchedule::class);
    }

    public function assignments() { return $this->hasMany(ExamSubjectAssignment::class); }
}

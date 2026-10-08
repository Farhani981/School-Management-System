<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamParticipation extends Model
{
    public $timestamps = false;
    protected $fillable = ['exam_id', 'class_id', 'section_id'];
    public function exam() { return $this->belongsTo(Exam::class); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function section() { return $this->belongsTo(SchoolSection::class, 'section_id'); }
}

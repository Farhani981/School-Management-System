<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSetting extends Model
{
    protected $fillable = ['pass_rule', 'overall_pass_percent', 'absent_counts_as_fail', 'grace_enabled', 'grace_max_marks', 'default_marks_window_days', 'report_instructions', 'principal_signatory', 'class_teacher_label','school_name','school_address','school_contact','school_logo_path'];

    protected function casts(): array
    {
        return ['overall_pass_percent' => 'decimal:2', 'grace_max_marks' => 'decimal:2', 'absent_counts_as_fail' => 'boolean', 'grace_enabled' => 'boolean'];
    }
}

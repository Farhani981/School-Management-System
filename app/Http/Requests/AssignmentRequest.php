<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['teacher_id' => ['required', 'exists:teachers,id,deleted_at,NULL'], 'class_id' => ['required', 'exists:school_classes,id'], 'section_id' => ['required', 'exists:school_sections,id'], 'subject_id' => ['required', Rule::exists('subjects', 'id')->where('status', 'Active')->whereNull('deleted_at')], 'session_id' => ['required', 'exists:academic_sessions,id'], 'is_class_teacher' => ['nullable', 'boolean'], 'weekly_periods' => ['required', 'integer', 'min:1', 'max:60']];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\StudentAttendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentAttendanceBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'school_class_id' => ['required', 'integer', 'exists:school_classes,id'], 'section_id' => ['required', 'integer', 'exists:school_sections,id'], 'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'rows' => ['required', 'array', 'min:1'], 'rows.*.student_id' => ['required', 'integer', 'distinct', 'exists:students,id,deleted_at,NULL'], 'rows.*.status' => ['required', Rule::in(StudentAttendance::STATUSES)], 'rows.*.remarks' => ['nullable', 'string', 'max:1000'], 'notify_absent' => ['nullable', 'boolean'],
        ];
    }
}

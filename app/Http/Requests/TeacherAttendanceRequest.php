<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Date-specific marking permission is checked after validation by AttendanceRecords.
        return $this->user()->can('view', $this->route('assignment'));
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.student_id' => ['required', 'integer', 'distinct'],
            'rows.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'leave'])],
            'rows.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

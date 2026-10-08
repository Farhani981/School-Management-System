<?php

namespace App\Http\Requests;

use App\Models\StudentAttendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'rows' => ['required', 'array', 'min:1'], 'rows.*.teacher_id' => ['required', 'integer', 'distinct', 'exists:teachers,id,deleted_at,NULL'], 'rows.*.status' => ['required', Rule::in(StudentAttendance::STATUSES)], 'rows.*.check_in' => ['nullable', 'required_with:rows.*.check_out', 'date_format:H:i'], 'rows.*.check_out' => ['nullable', 'date_format:H:i', 'after_or_equal:rows.*.check_in'], 'rows.*.remarks' => ['nullable', 'string', 'max:1000']];
    }
}

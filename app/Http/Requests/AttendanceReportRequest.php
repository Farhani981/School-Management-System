<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['from' => $this->input('from', now()->startOfMonth()->toDateString()), 'to' => $this->input('to', now()->toDateString())]);
    }

    public function rules(): array
    {
        return ['from' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today', function ($attribute, $value, $fail) {
            if (strtotime($value) && strtotime($this->from) && (strtotime($value) - strtotime($this->from)) / 86400 > 365) {
                $fail('Choose a date range of no more than 366 days.');
            }
        }], 'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'], 'section_id' => ['nullable', 'integer', 'exists:school_sections,id'], 'academic_session_id' => ['nullable', 'integer', 'exists:academic_sessions,id']];
    }
}

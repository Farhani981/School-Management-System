<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherActionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route()->getName() === 'teachers.setup' && is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()->getName()) {
            'teachers.setup' => ['type' => ['required', Rule::in(['department', 'designation', 'subject', 'leave_type'])], 'name' => ['required', 'string', 'max:100'], 'annual_days' => ['required_if:type,leave_type', 'nullable', 'integer', 'min:0', 'max:366'], 'is_paid' => ['nullable', 'boolean']],
            'teachers.leaves.review' => ['status' => ['required', Rule::in(['Approved', 'Rejected'])], 'remarks' => ['required', 'string', 'max:2000']],
            'teachers.payroll.advances' => ['teacher_id' => ['required', 'exists:teachers,id,deleted_at,NULL'], 'month' => ['required', 'date_format:Y-m'], 'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'], 'notes' => ['nullable', 'string', 'max:2000']],
            'teachers.payroll.attendance' => ['teacher_id' => ['required', 'exists:teachers,id,deleted_at,NULL'], 'date' => ['required', 'date', 'before_or_equal:today'], 'status' => ['required', Rule::in(['Present', 'Absent', 'Leave'])]],
            'teachers.import.store' => ['file' => ['required', 'file', 'mimes:xlsx,csv', 'max:10240']],
            default => [],
        };
    }
}

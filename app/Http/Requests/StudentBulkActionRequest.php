<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentBulkActionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['students' => ['required', 'array', 'min:1', 'max:100'], 'students.*' => ['integer', 'distinct', Rule::exists('students', 'id')->whereNull('deleted_at')], 'action' => ['required', Rule::in(['status', 'id_cards'])], 'status' => ['required_if:action,status', 'nullable', Rule::in(Student::STATUSES)]];
    }
}

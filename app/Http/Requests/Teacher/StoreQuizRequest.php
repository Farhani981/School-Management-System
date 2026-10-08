<?php

namespace App\Http\Requests\Teacher;

use App\Models\AcademicSession;
use App\Models\Quiz;
use App\Models\TeacherAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Quiz::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', Rule::in(array_keys(Quiz::TYPES))],
            'assignment_id' => [
                'required',
                'integer',
                Rule::exists('teacher_assignments', 'id')->where(function ($q) {
                    $q->where('teacher_id', $this->user()->teacher?->id ?? 0);
                }),
            ],
            'date' => ['required', 'date'],
            'total_marks' => ['required', 'numeric', 'min:0.5', 'max:1000'],
            'passing_marks' => ['required', 'numeric', 'min:0', 'lte:total_marks'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'published'])],
        ];
    }

    public function messages(): array
    {
        return [
            'assignment_id.required' => 'Please select an assigned class and subject.',
            'assignment_id.exists' => 'The selected class and subject is not assigned to your teacher account.',
            'passing_marks.lte' => 'Passing marks cannot exceed total marks.',
            'total_marks.min' => 'Total marks must be greater than zero.',
        ];
    }
}

<?php

namespace App\Http\Requests\Teacher;

use App\Models\Quiz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quiz = $this->route('quiz');
        return $this->user()->can('update', $quiz);
    }

    public function rules(): array
    {
        /** @var Quiz $quiz */
        $quiz = $this->route('quiz');
        $hasMarks = $quiz->hasMarksEntered();

        $rules = [
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', Rule::in(array_keys(Quiz::TYPES))],
            'date' => ['required', 'date'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'published', 'closed'])],
        ];

        if ($hasMarks) {
            // Locked fields: total_marks cannot be changed once marks exist
            $rules['passing_marks'] = ['required', 'numeric', 'min:0', 'lte:' . (float) $quiz->total_marks];
        } else {
            $rules['assignment_id'] = [
                'required',
                'integer',
                Rule::exists('teacher_assignments', 'id')->where(function ($q) {
                    $q->where('teacher_id', $this->user()->teacher?->id ?? 0);
                }),
            ];
            $rules['total_marks'] = ['required', 'numeric', 'min:0.5', 'max:1000'];
            $rules['passing_marks'] = ['required', 'numeric', 'min:0', 'lte:total_marks'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'passing_marks.lte' => 'Passing marks cannot exceed total marks.',
            'total_marks.min' => 'Total marks must be greater than zero.',
        ];
    }
}

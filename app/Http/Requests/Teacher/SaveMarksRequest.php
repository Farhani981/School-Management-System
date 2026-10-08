<?php

namespace App\Http\Requests\Teacher;

use App\Models\Quiz;
use Illuminate\Foundation\Http\FormRequest;

class SaveMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quiz = $this->route('quiz');
        return $this->user()->can('enterMarks', $quiz);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:draft,finalize'],
            'reason' => ['nullable', 'string', 'max:500'],
            'rows' => ['required', 'array'],
            'rows.*.student_id' => ['required', 'integer'],
            'rows.*.attendance_status' => ['required', 'string', 'in:present,absent,exempt'],
            'rows.*.marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'rows.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}

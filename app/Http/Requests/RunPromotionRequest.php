<?php

namespace App\Http\Requests;

use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunPromotionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $decisions = (array) $this->input('decisions', []);
        $transfer = in_array('promote', $decisions, true) || in_array('detain', $decisions, true);
        $promote = in_array('promote', $decisions, true);
        return [
            'students' => ['required', 'array', 'min:1', 'max:50'],
            'students.*' => ['integer', 'distinct', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'decisions' => ['required', 'array', 'max:50'],
            'decisions.*' => ['required', Rule::in(array_keys(Promotion::ACTIONS))],
            'target_session_id' => [Rule::requiredIf($transfer), 'nullable', 'integer', 'exists:academic_sessions,id'],
            'target_class_id' => [Rule::requiredIf($promote), 'nullable', 'integer', 'exists:school_classes,id'],
            'target_section_id' => [Rule::requiredIf($promote), 'nullable', 'integer', 'exists:school_sections,id'],
            'current_session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'current_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'current_section_id' => ['nullable', 'integer', 'exists:school_sections,id'],
        ];
    }
    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) return;
            $students = $this->input('students', []);
            $decisions = $this->input('decisions', []);
            if (!is_array($students) || !is_array($decisions)) return;
            if (array_diff(array_map('strval', $students), array_map('strval', array_keys($decisions))) || array_diff(array_map('strval', array_keys($decisions)), array_map('strval', $students))) {
                $validator->errors()->add('decisions', 'Choose one action for each selected student.');
            }
        }];
    }
}

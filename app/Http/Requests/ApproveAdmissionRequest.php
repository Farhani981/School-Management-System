<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ApproveAdmissionRequest extends StoreStudentRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['class_id'] = ['required', 'integer', 'exists:school_classes,id'];
        $rules['section_id'] = ['required', 'integer', 'exists:school_sections,id'];
        $rules['academic_session_id'] = ['required', 'integer', 'exists:academic_sessions,id'];
        $rules['status'] = ['required', Rule::in(['Active'])];
        return $rules;
    }
}

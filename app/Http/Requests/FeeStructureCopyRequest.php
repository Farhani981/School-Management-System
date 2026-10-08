<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FeeStructureCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fees.manage');
    }

    public function rules(): array
    {
        return ['academic_session_id' => 'required|integer|exists:academic_sessions,id', 'school_class_id' => 'required|integer|exists:school_classes,id', 'from_academic_session_id' => 'required|integer|exists:academic_sessions,id', 'from_school_class_id' => 'required|integer|exists:school_classes,id'];
    }
}

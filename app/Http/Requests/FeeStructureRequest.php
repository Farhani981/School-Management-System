<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fees.manage');
    }

    public function rules(): array
    {
        return ['academic_session_id' => 'required|integer|exists:academic_sessions,id', 'school_class_id' => 'required|integer|exists:school_classes,id', 'amounts' => 'required|array|min:1', 'amounts.*' => 'nullable|numeric|decimal:0,2|min:0|max:99999999.99'];
    }
}

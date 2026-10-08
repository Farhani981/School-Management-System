<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdmissionApplicationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['applicant_name' => ['required', 'string', 'max:255'], 'father_name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:30'], 'applying_for_class_id' => ['required', 'integer', 'exists:school_classes,id'], 'previous_school' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}

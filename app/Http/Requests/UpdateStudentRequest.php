<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateStudentRequest extends StoreStudentRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['admission_no'] = ['required', 'string', 'max:255', Rule::unique('students', 'admission_no')->ignore($this->route('student'))];

        return $rules;
    }
}

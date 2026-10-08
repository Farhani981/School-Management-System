<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['is_class_teacher' => ['required', 'boolean']];
    }
}

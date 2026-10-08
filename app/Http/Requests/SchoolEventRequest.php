<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255', 'description' => 'nullable|string|max:3000',
            'date' => 'required|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:date',
            'type' => ['required', Rule::in(['holiday', 'meeting', 'exam', 'event'])],
            'color' => ['required', Rule::in(['blue', 'green', 'coral', 'pink', 'amber'])],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        if (is_string($this->name)) {
            $data['name'] = trim($this->name);
        }
        if (is_string($this->code)) {
            $data['code'] = strtoupper(trim($this->code));
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        $subject = $this->route('subject');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('subjects')->ignore($subject?->id)],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('subjects')->ignore($subject?->id)],
            'type' => ['required', Rule::in(['theory', 'practical', 'both'])],
            'category' => ['required', Rule::in(['compulsory', 'optional', 'elective'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }
}

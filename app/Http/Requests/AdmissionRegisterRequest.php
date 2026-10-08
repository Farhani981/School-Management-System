<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdmissionRegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        foreach (['from', 'to'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
                try {
                    $date = \Carbon\Carbon::createFromFormat('!d-m-Y', $value);
                    if ($date->format('d-m-Y') === $value) $this->merge([$field => $date->format('Y-m-d')]);
                } catch (\Throwable) {}
            }
        }
    }
    public function rules(): array
    {
        return ['from' => ['nullable', 'date'], 'to' => array_merge(['nullable', 'date'], $this->filled('from') ? ['after_or_equal:from'] : []), 'class_id' => ['nullable', 'integer', 'exists:school_classes,id']];
    }
}

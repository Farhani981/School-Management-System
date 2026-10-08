<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin', 'Principal']) ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['category', 'description', 'reference'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([
                    $field => trim($this->input($field)),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:5000'],
            'amount' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:0.01',
                'max:99999999.99',
            ],
            'expense_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'payment_method' => [
                'required',
                Rule::in(['Cash', 'Bank Transfer', 'Cheque']),
            ],
            'reference' => ['nullable', 'string', 'max:150'],
        ];
    }
}
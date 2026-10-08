<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FeeSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fees.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['voucher_prefix' => strtoupper(trim((string) $this->voucher_prefix)), 'receipt_prefix' => strtoupper(trim((string) $this->receipt_prefix))]);
    }

    public function rules(): array
    {
        return [
            'due_day' => 'required|integer|min:1|max:31', 'fine_type' => ['required', Rule::in(['none', 'fixed', 'per_day'])],
            'fine_amount' => 'nullable|numeric|decimal:0,2|min:0|max:99999999.99', 'grace_days' => 'required|integer|min:0|max:365', 'fine_max_cap' => 'nullable|numeric|decimal:0,2|min:0|max:99999999.99',
            'voucher_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z][A-Z0-9-]*$/'], 'receipt_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z][A-Z0-9-]*$/'],
            'sibling_discount_enabled' => 'required|boolean', 'sibling_discount_type' => ['required', Rule::in(['fixed', 'percent'])], 'sibling_discount_value' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.($this->sibling_discount_type === 'percent' ? 100 : '99999999.99')],
        ];
    }
}

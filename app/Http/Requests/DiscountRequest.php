<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fees.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->name)]);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100', Rule::unique('discounts')->ignore($this->route('discount'))], 'type' => ['required', Rule::in(['fixed', 'percent'])], 'value' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.($this->type === 'percent' ? 100 : '99999999.99')], 'is_active' => 'required|boolean'];
    }
}

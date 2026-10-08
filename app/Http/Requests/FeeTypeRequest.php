<?php

namespace App\Http\Requests;

use App\Models\FeeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FeeTypeRequest extends FormRequest
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
        return ['name' => ['required', 'string', 'max:100', Rule::unique('fee_types')->ignore($this->route('type'))], 'frequency' => ['required', Rule::in(array_keys(FeeType::FREQUENCIES))], 'is_active' => 'required|boolean'];
    }
}

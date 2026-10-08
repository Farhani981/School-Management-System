<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StudentFeeOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fees.manage');
    }

    public function rules(): array
    {
        return ['fee_type_id' => ['required', 'integer', Rule::exists('fee_types', 'id')->whereNull('deleted_at')->where('is_active', true)], 'amount' => 'required|numeric|decimal:0,2|min:0|max:99999999.99', 'reason' => 'required|string|max:1000'];
    }
}

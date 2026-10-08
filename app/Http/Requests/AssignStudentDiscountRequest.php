<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssignStudentDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fees.manage');
    }

    public function rules(): array
    {
        return ['student_id' => ['required', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')], 'discount_id' => ['required', 'integer', Rule::exists('discounts', 'id')->where('is_active', true)], 'fee_type_id' => ['nullable', 'integer', Rule::exists('fee_types', 'id')->whereNull('deleted_at')->where('is_active', true)], 'valid_from' => 'required|date_format:Y-m-d', 'valid_to' => 'nullable|date_format:Y-m-d|after_or_equal:valid_from'];
    }
}

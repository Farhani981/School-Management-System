<?php

namespace App\Http\Requests;

use App\Models\SalaryStructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $teacher = $this->route('teacher');

        return [
            'name' => ['required', 'string', 'max:255'],
            'cnic' => ['required', 'regex:/^\d{5}-\d{7}-\d$/', Rule::unique('teachers')->ignore($teacher?->id)],
            'gender' => ['required', Rule::in(['Male', 'Female', 'Other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['required', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'], 'emergency_contact' => ['nullable', 'string', 'max:100'],
            'department_id' => ['required', 'exists:departments,id'], 'designation_id' => ['required', 'exists:designations,id'],
            'joining_date' => ['required', 'date'], 'status' => ['required', Rule::in(['Active', 'Inactive', 'On Leave'])],
            'qualification' => ['nullable', 'string', 'max:5000'], 'experience' => ['nullable', 'string', 'max:5000'],
            'basic_salary' => ['required', 'numeric', 'min:1', 'max:99999999.99'],
            'house_allowance' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'medical_allowance' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'other_allowance' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'fixed_deductions' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', function ($attribute, $value, $fail) {
                $gross = 0;
                foreach (['basic_salary', ...SalaryStructure::ALLOWANCE_FIELDS] as $field) {
                    $amount = $this->input($field, 0);
                    if ($amount !== null && ! is_numeric($amount)) {
                        return;
                    }
                    $gross += (int) round((float) $amount * 100);
                }
                if (is_numeric($value) && (int) round((float) $value * 100) > $gross) {
                    $fail('Fixed deductions must not exceed gross salary.');
                }
            }],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_no' => ['nullable', 'string', 'max:100'],
            'create_login' => ['nullable', 'boolean'],
            'login_email' => [Rule::requiredIf($this->boolean('create_login') || (bool) $teacher?->user_id), 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($teacher?->user_id)],
            'password' => [Rule::requiredIf($this->boolean('create_login') && ! $teacher?->user_id), 'nullable', 'string', 'min:8', 'confirmed'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'documents' => ['nullable', 'array', 'max:10'], 'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}

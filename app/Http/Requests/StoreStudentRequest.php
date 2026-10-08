<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = [];
        // New admissions belong to the current session when the form omits it.
        // Preserve an explicit session and never silently move an existing student.
        if (! $this->route('student') && ! $this->filled('academic_session_id')) {
            $currentSession = \App\Models\AcademicSession::where('is_current', true)->value('id');
            if ($currentSession) $normalized['academic_session_id'] = $currentSession;
        }
        foreach (['date_of_birth', 'admission_date'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
                try {
                    $date = \Carbon\Carbon::createFromFormat('!d-m-Y', $value);
                    if ($date->format('d-m-Y') === $value) $normalized[$field] = $date->format('Y-m-d');
                } catch (\Throwable) {}
            }
        }
        $this->merge($normalized);
    }
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'admission_no' => ['nullable', 'string', 'max:255', Rule::unique('students', 'admission_no')],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'father_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', Rule::in(['Male', 'Female', 'Other'])],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:2000'],
            'admission_date' => ['required', 'date', 'after_or_equal:date_of_birth'],
            'class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:school_sections,id'],
            'academic_session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'b_form_number' => ['nullable', 'string', 'max:30'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['nullable', 'required_with:guardian_relationship,guardian_phone', 'string', 'max:255'],
            'guardian_relationship' => ['nullable', 'required_with:guardian_name', Rule::in(\App\Models\Student::RELATIONSHIPS)],
            'family_id' => ['nullable', 'string', 'max:100'],
            'guardian_phone' => ['nullable', 'required_with:guardian_name', 'string', 'max:30'],
            'emergency_contact' => ['nullable', 'string', 'max:30'],
            'permanent_address' => ['nullable', 'string', 'max:2000'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'email' => ['nullable', 'email', 'max:255'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'last_class' => ['nullable', 'string', 'max:100'],
            'leaving_reason' => ['nullable', 'string', 'max:1000'],
            'blood_group' => ['nullable', 'string', 'max:20'],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'height' => ['nullable', 'string', 'max:50'],
            'weight' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string', 'max:3000'],
            'roll_number' => ['required', 'string', 'max:255', Rule::unique('students', 'roll_number')->where('class_id', $this->input('class_id'))->where('section_id', $this->input('section_id'))->where('academic_session_id', $this->input('academic_session_id'))->ignore($this->route('student'))],
            'status' => ['required', Rule::in(\App\Models\Student::STATUSES)],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\LibraryMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LibraryMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('member') ? $this->user()->can('update', $this->route('member')) : $this->user()->can('create', LibraryMember::class);
    }

    public function rules(): array
    {
        $rules = [
            'registration_date' => 'required|date_format:Y-m-d',
            'borrowing_limit' => 'required|integer|min:1|max:100',
            'status' => ['required', Rule::in(LibraryMember::STATUSES)],
        ];
        if ($this->route('member')) {
            // Membership stays linked to the same borrower throughout its life.
            return $rules + ['member_type' => 'prohibited', 'student_id' => 'prohibited', 'teacher_id' => 'prohibited', 'member_id' => 'prohibited'];
        }

        return $rules + [
            'member_type' => ['required', Rule::in(LibraryMember::TYPES)],
            'member_id' => 'prohibited',
            'student_id' => ['nullable', 'required_if:member_type,Student', 'prohibited_unless:member_type,Student', 'integer',
                Rule::exists('students', 'id')->whereNull('deleted_at')->where('status', 'Active'), Rule::unique('library_members', 'student_id')],
            'teacher_id' => ['nullable', 'required_if:member_type,Teacher', 'prohibited_unless:member_type,Teacher', 'integer',
                Rule::exists('teachers', 'id')->whereNull('deleted_at')->where('status', 'Active'), Rule::unique('library_members', 'teacher_id')],
        ];
    }

    public function messages(): array
    {
        return ['student_id.unique' => 'This student is already a library member.', 'teacher_id.unique' => 'This teacher is already a library member.',
            'student_id.exists' => 'Choose an active student.', 'teacher_id.exists' => 'Choose an active teacher.'];
    }
}

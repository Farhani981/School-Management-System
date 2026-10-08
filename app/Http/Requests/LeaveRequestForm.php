<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeaveRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['teacher_id' => ['required', 'exists:teachers,id,deleted_at,NULL'], 'leave_type_id' => ['required', 'exists:leave_types,id'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:'.substr($this->input('start_date', ''), 0, 4).'-12-31'], 'reason' => ['required', 'string', 'max:2000']];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\LibraryBookCopy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LibraryReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('receive', $this->route('transaction'));
    }

    public function rules(): array
    {
        return ['return_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.$this->route('transaction')->issue_date->format('Y-m-d')],
            'condition' => ['required', Rule::in([...LibraryBookCopy::CONDITIONS, 'Lost'])], 'return_notes' => 'nullable|string|max:2000'];
    }
}

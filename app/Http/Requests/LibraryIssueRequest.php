<?php

namespace App\Http\Requests;

use App\Models\LibraryTransaction;
use Illuminate\Foundation\Http\FormRequest;

class LibraryIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', LibraryTransaction::class);
    }

    public function rules(): array
    {
        return ['member_id' => 'required|integer|exists:library_members,id', 'book_id' => 'required|integer|exists:library_books,id',
            'book_copy_id' => 'required|integer|exists:library_book_copies,id', 'issue_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'due_date' => 'nullable|date_format:Y-m-d|after_or_equal:issue_date', 'notes' => 'nullable|string|max:2000'];
    }
}

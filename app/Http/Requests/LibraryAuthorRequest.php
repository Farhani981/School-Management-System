<?php

namespace App\Http\Requests;

use App\Models\LibraryAuthor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LibraryAuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $author = $this->route('author');

        return $author
            ? $this->user()->can('update', $author)
            : $this->user()->can('create', LibraryAuthor::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => preg_replace('/\s+/u', ' ', trim($this->input('name')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'biography' => ['nullable', 'string', 'max:5000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }
}

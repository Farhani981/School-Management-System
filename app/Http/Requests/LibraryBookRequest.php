<?php

namespace App\Http\Requests;

use App\Models\LibraryBook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LibraryBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('book') ? $this->user()->can('update', $this->route('book')) : $this->user()->can('create', LibraryBook::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'author_name', 'publisher_name'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => preg_replace('/\s+/u', ' ', trim($this->input($field)))]);
            }
        }
    }

    public function rules(): array
    {
        return ['title' => 'required|string|max:255',
            'author_id' => 'nullable|required_without:author_name|integer|exists:library_authors,id',
            'author_name' => 'nullable|required_without:author_id|string|max:150',
            'publisher_name' => 'nullable|string|max:150', 'isbn' => ['nullable', 'string', 'max:30', Rule::unique('library_books', 'isbn')->ignore($this->route('book'))],
            'description' => 'nullable|string|max:5000', 'rack' => 'nullable|string|max:100',
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240'];
    }

    public function messages(): array
    {
        return [
            'cover.max' => 'The book cover must be 10 MB or smaller.',
            'cover.image' => 'Please upload a valid book cover image.',
            'cover.mimes' => 'The book cover must be a JPG, PNG or WebP image.',
        ];
    }
}

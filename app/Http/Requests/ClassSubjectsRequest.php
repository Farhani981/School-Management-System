<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassSubjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->routeIs('subjects.classes.store')) {
            return;
        }
        $rows = [];
        $selected = $this->input('selected', []);
        if (is_array($selected)) {
            foreach ($selected as $id) {
                if (! is_scalar($id)) {
                    continue;
                }
                $row = $this->input('subjects.'.(string) $id, []);
                $rows[] = array_merge(is_array($row) ? $row : [], ['subject_id' => $id]);
            }
        }
        $this->merge(['subjects' => $rows]);
    }

    public function rules(): array
    {
        if ($this->routeIs('subjects.classes.copy')) {
            return ['school_class_id' => ['required', 'exists:school_classes,id'], 'source_class_id' => ['required', 'exists:school_classes,id', 'different:school_class_id']];
        }

        return [
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'selected' => ['nullable', 'array'],
            'selected.*' => ['integer', 'distinct', Rule::exists('subjects', 'id')->where('status', 'Active')->whereNull('deleted_at')],
            'subjects' => ['nullable', 'array'],
            'subjects.*.subject_id' => ['required', 'integer', 'distinct', Rule::exists('subjects', 'id')->where('status', 'Active')->whereNull('deleted_at')],
            'subjects.*.total_marks' => ['required', 'numeric', 'min:1', 'max:99999999.99'],
            'subjects.*.pass_marks' => ['required', 'numeric', 'min:0', 'lte:subjects.*.total_marks'],
            'subjects.*.practical_marks' => ['required', 'numeric', 'min:0', 'lte:subjects.*.total_marks'],
            'subjects.*.is_compulsory' => ['required', 'boolean'],
            'subjects.*.display_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}

<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SubjectMark;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassSubjects
{
    public function save(int $classId, array $rows): void
    {
        DB::transaction(function () use ($classId, $rows) {
            $class = SchoolClass::whereKey($classId)->lockForUpdate()->firstOrFail();
            $payload = [];
            $subjects = Subject::whereIn('id', array_column($rows, 'subject_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($rows as $row) {
                if (($subjects[$row['subject_id']]->status ?? null) !== 'Active') {
                    throw ValidationException::withMessages(['subjects' => 'Only active subjects can be assigned. Refresh the page and try again.']);
                }
                $id = $row['subject_id'];
                $payload[$id] = Arr::only($row, ['total_marks', 'pass_marks', 'practical_marks', 'is_compulsory', 'display_order']);
            }
            // Keep existing inactive links, which are deliberately absent from this form.
            foreach ($class->subjects()->where('status', 'Inactive')->get() as $subject) {
                $payload[$subject->id] = $subject->pivot->only(['total_marks', 'pass_marks', 'practical_marks', 'is_compulsory', 'display_order']);
            }
            $removed = array_diff($class->subjects()->pluck('subjects.id')->all(), array_keys($payload));
            if (SubjectMark::where('school_class_id', $classId)->whereIn('subject_id', $removed)->exists()) {
                throw ValidationException::withMessages(['subjects' => 'A subject with recorded marks cannot be removed from this class.']);
            }
            $class->subjects()->sync($payload);
        }, 3);
    }

    public function copy(int $target, int $source): void
    {
        DB::transaction(function () use ($target, $source) {
            SchoolClass::whereIn('id', [$target, $source])->orderBy('id')->lockForUpdate()->get();
            $subjects = SchoolClass::findOrFail($source)->subjects()->where('status', 'Active')->get();
            if ($subjects->isEmpty()) {
                throw ValidationException::withMessages(['source_class_id' => 'The source class has no active subjects to copy.']);
            }
            $rows = $subjects->map(fn ($subject) => ['subject_id' => $subject->id] + $subject->pivot->only(['total_marks', 'pass_marks', 'practical_marks', 'is_compulsory', 'display_order']))->all();
            $this->save($target, $rows);
        }, 3);
    }
}

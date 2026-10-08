<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentPromotion
{
    public function promote(Student $student, array $destination, string $action = 'promote', ?int $actor = null): void
    {
        if ($student->status !== 'Active') throw ValidationException::withMessages(['promotion' => 'Only active students can be promoted.']);
        if ((string) $student->class_id === (string) $destination['class_id'] && (string) $student->academic_session_id === (string) $destination['academic_session_id']) {
            throw ValidationException::withMessages(['promotion' => 'Select a different class or academic session.']);
        }
        if (DB::table('students')->where('class_id', $destination['class_id'])->where('section_id', $destination['section_id'])->where('academic_session_id', $destination['academic_session_id'])->where('roll_number', $destination['roll_number'])->where('id', '!=', $student->id)->exists()) {
            throw ValidationException::withMessages(['roll_number' => 'This roll number is already reserved in the destination class.']);
        }
        $changes = [];
        $from = $student->only(['academic_session_id', 'class_id', 'section_id']);
        foreach (['class_id', 'section_id', 'academic_session_id', 'roll_number'] as $field) {
            $changes[$field] = ['from' => $student->$field, 'to' => $destination[$field]];
            $student->$field = $destination[$field];
        }
        $student->save();
        $student->histories()->create(['event' => $action === 'detain' ? 'Student detained' : 'Student promoted', 'changes' => $changes, 'notes' => $destination['notes'] ?? null]);
        $this->record($student, $from, $action, $actor ?? auth()->id());
    }

    public function record(Student $student, array $from, string $action, ?int $actor, bool $terminal = false): void
    {
        $student->promotions()->create(['from_session_id' => $from['academic_session_id'], 'from_class_id' => $from['class_id'], 'from_section_id' => $from['section_id'], 'to_session_id' => $terminal ? null : $student->academic_session_id, 'to_class_id' => $terminal ? null : $student->class_id, 'to_section_id' => $terminal ? null : $student->section_id, 'action' => $action, 'promoted_by' => $actor, 'date' => now()->toDateString()]);
    }
}

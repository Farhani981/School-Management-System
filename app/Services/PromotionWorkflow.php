<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionWorkflow
{
    public function run(array $data, ?int $actor): void
    {
        DB::transaction(function () use ($data, $actor) {
            $students = Student::whereIn('id', $data['students'])->orderBy('id')->lockForUpdate()->get();
            if ($students->count() !== count($data['students'])) throw ValidationException::withMessages(['students' => 'Selection changed. Reload students before continuing.']);
            foreach ($students as $student) {
                if ($student->status !== 'Active') throw ValidationException::withMessages(['students' => 'Only active students may be processed.']);
                foreach (['session_id' => 'academic_session_id', 'class_id' => 'class_id', 'section_id' => 'section_id'] as $input => $field) {
                    if (!empty($data['current_'.$input]) && (string) $data['current_'.$input] !== (string) $student->$field) throw ValidationException::withMessages(['students' => 'A selected student no longer belongs to the current assignment.']);
                }
                $action = $data['decisions'][$student->id];
                $from = $student->only(['academic_session_id', 'class_id', 'section_id']);
                if (in_array($action, ['left', 'passed_out'])) {
                    app(StudentRecords::class)->save($student, ['status' => $action === 'left' ? 'Left' : 'Passed out']);
                    app(StudentPromotion::class)->record($student, $from, $action, $actor, true);
                    continue;
                }
                $session = (int) $data['target_session_id'];
                $class = $action === 'detain' ? $student->class_id : (int) $data['target_class_id'];
                $section = $action === 'detain' ? $student->section_id : (int) $data['target_section_id'];
                if (!$class || !$section) throw ValidationException::withMessages(['students' => 'Assign a current class and section before detaining a student.']);
                if ($session === (int) $student->academic_session_id) throw ValidationException::withMessages(['target_session_id' => 'Choose a different academic session.']);
                if ($action === 'promote' && $class === (int) $student->class_id) throw ValidationException::withMessages(['target_class_id' => 'Choose a different class to promote, or use Detain.']);
                $destination = ['academic_session_id' => $session, 'class_id' => $class, 'section_id' => $section, 'roll_number' => app(RollNumbers::class)->next($session, $class, $section)];
                app(StudentPromotion::class)->promote($student, $destination, $action, $actor);
            }
        }, 5);
    }
}

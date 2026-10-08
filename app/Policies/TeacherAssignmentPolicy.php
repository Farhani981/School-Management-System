<?php

namespace App\Policies;

use App\Models\TeacherAssignment;
use App\Models\User;

class TeacherAssignmentPolicy
{
    public function view(User $user, TeacherAssignment $assignment): bool
    {
        return $user->hasRole('Teacher') && $user->teacher?->status === 'Active'
            && $assignment->teacher_id === $user->teacher->id;
    }

    public function mark(User $user, TeacherAssignment $assignment, ?string $date = null): bool
    {
        return $this->view($user, $assignment) && app(\App\Services\FirstPeriodAttendance::class)
            ->allows($user, $assignment->class_id, $assignment->section_id, $assignment->session_id, $date);
    }
}

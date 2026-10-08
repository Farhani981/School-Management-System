<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;

class StudentPolicy
{
    /**
     * Determine whether the user can view the student profile.
     */
    public function view(User $user, Student $student): bool
    {
        if ($user->hasAnyRole(['Admin', 'Principal'])) {
            return true;
        }

        if ($user->hasRole('Teacher') && $user->teacher && $user->teacher->status === 'Active') {
            if (! $student->class_id) {
                return false;
            }

            return TeacherAssignment::where('teacher_id', $user->teacher->id)
                ->where('class_id', $student->class_id)
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can view student fees.
     * Teachers must NEVER see it.
     */
    public function viewFees(User $user, Student $student): bool
    {
        if ($user->hasRole('Teacher')) {
            return false;
        }

        return $user->can('fees.view') || $user->can('fees.manage') || $user->hasAnyRole(['Admin', 'Principal']);
    }

    /**
     * Determine whether the user can view/manage student documents.
     * Teachers must not see documents.
     */
    public function viewDocuments(User $user, Student $student): bool
    {
        if ($user->hasRole('Teacher')) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'Principal']);
    }

    /**
     * Determine whether the user can update the student.
     */
    public function update(User $user, Student $student): bool
    {
        return $user->hasAnyRole(['Admin', 'Principal']);
    }

    /**
     * Determine whether the user can change student status.
     */
    public function updateStatus(User $user, Student $student): bool
    {
        return $user->hasAnyRole(['Admin', 'Principal']);
    }

    /**
     * Determine whether the user can manage portal login.
     */
    public function manageLogin(User $user, Student $student): bool
    {
        return $user->hasAnyRole(['Admin', 'Principal']);
    }
}

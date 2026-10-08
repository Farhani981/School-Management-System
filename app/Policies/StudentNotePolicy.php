<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\StudentNote;
use App\Models\TeacherAssignment;
use App\Models\User;

class StudentNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Teacher', 'Admin', 'Principal']);
    }

    public function view(User $user, StudentNote $note): bool
    {
        if ($user->hasAnyRole(['Admin', 'Principal'])) {
            return true;
        }

        if (! $user->hasRole('Teacher') || ! $user->teacher) {
            return false;
        }

        // Only the author teacher can view their private notes
        return $note->teacher_id === $user->teacher->id;
    }

    public function create(User $user, Student $student): bool
    {
        if ($user->hasAnyRole(['Admin', 'Principal'])) {
            return true;
        }

        if (! $user->hasRole('Teacher') || ! $user->teacher) {
            return false;
        }

        // Must have an active assignment for this student's class and section
        return TeacherAssignment::where('teacher_id', $user->teacher->id)
            ->where('class_id', $student->class_id)
            ->where('section_id', $student->section_id)
            ->exists();
    }

    public function update(User $user, StudentNote $note): bool
    {
        if ($user->hasAnyRole(['Admin', 'Principal'])) {
            return true;
        }

        return $user->hasRole('Teacher')
            && $user->teacher
            && $note->teacher_id === $user->teacher->id;
    }

    public function delete(User $user, StudentNote $note): bool
    {
        return $this->update($user, $note);
    }
}

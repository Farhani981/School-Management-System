<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\TeacherAssignment;
use App\Models\User;

class QuizPolicy
{
    /**
     * Check if a teacher has active assignment for the given class, section, subject, and session.
     */
    protected function hasAssignment(User $user, Quiz $quiz): bool
    {
        if (! $user->hasRole('Teacher') || $user->teacher?->status !== 'Active') {
            return false;
        }

        // Must match this teacher's assignment
        return TeacherAssignment::where('teacher_id', $user->teacher->id)
            ->where('class_id', $quiz->class_id)
            ->where('section_id', $quiz->section_id)
            ->where('subject_id', $quiz->subject_id)
            ->where('session_id', $quiz->session_id)
            ->exists();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Teacher') && $user->teacher?->status === 'Active';
    }

    public function view(User $user, Quiz $quiz): bool
    {
        if ($quiz->exam_subject_id) {
            // Archived exam assessments are unavailable through standalone quiz routes.
            return false;
        }
        return $this->hasAssignment($user, $quiz);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Teacher') && $user->teacher?->status === 'Active';
    }

    public function update(User $user, Quiz $quiz): bool
    {
        // Preserve archived exam assessments; only standalone quizzes can be changed.
        return ! $quiz->exam_subject_id && $this->hasAssignment($user, $quiz);
    }

    public function enterMarks(User $user, Quiz $quiz): bool
    {
        // Preserve archived exam assessments; only standalone quizzes can be changed.
        return ! $quiz->exam_subject_id && $this->hasAssignment($user, $quiz);
    }

    public function duplicate(User $user, Quiz $quiz): bool
    {
        // Preserve archived exam assessments; only standalone quizzes can be changed.
        return ! $quiz->exam_subject_id && $this->hasAssignment($user, $quiz);
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        if ($quiz->exam_subject_id || ! $this->hasAssignment($user, $quiz)) {
            return false;
        }

        // Only allow delete if no marks have been entered
        return ! $quiz->hasMarksEntered();
    }
}

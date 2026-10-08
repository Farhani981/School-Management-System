<?php

namespace App\Policies;

use App\Models\{Exam, User};

class ExamPolicy
{
    public function viewAny(User $user): bool { return $user->hasAnyRole(['Admin', 'Principal']); }
    public function view(User $user, Exam $exam): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $this->viewAny($user); }
    public function update(User $user, Exam $exam): bool { return $this->viewAny($user); }
    public function cancel(User $user, Exam $exam): bool { return $this->viewAny($user); }
}

<?php

namespace App\Policies;

use App\Models\LibraryMember;
use App\Models\User;

class LibraryMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Librarian']);
    }

    public function view(User $user, LibraryMember $member): bool
    {
        return $this->viewAny($user)
            || ($user->hasRole('Student') && $member->member_type === 'Student' && $member->student?->user_id === $user->id)
            || ($user->hasRole('Teacher') && $member->member_type === 'Teacher' && $member->teacher?->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, LibraryMember $member): bool
    {
        return $this->viewAny($user);
    }
}

<?php

namespace App\Policies;

use App\Models\User;

class LibraryBookCopyPolicy
{
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Librarian']);
    }
}

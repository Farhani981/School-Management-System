<?php

namespace App\Policies;

use App\Models\LibraryBook;
use App\Models\User;

class LibraryBookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Librarian', 'Teacher', 'Student']);
    }

    public function view(User $user, LibraryBook $book): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, LibraryBook $book): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, LibraryBook $book): bool
    {
        return $this->create($user);
    }
}

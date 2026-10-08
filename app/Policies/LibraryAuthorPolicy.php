<?php

namespace App\Policies;

use App\Models\LibraryAuthor;
use App\Models\User;

class LibraryAuthorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Librarian']);
    }

    public function view(User $user, LibraryAuthor $author): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, LibraryAuthor $author): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, LibraryAuthor $author): bool
    {
        return $this->create($user);
    }
}

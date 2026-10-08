<?php

namespace App\Policies;

use App\Models\LibraryTransaction;
use App\Models\User;

class LibraryTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Librarian']);
    }

    public function view(User $user, LibraryTransaction $transaction): bool
    {
        return $this->viewAny($user) || $user->can('view', $transaction->member);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function receive(User $user, LibraryTransaction $transaction): bool
    {
        return $this->viewAny($user);
    }
}

<?php

namespace App\Services;

use App\Models\User;

class LoginDestination
{
    public static function for(User $user): string
    {
        if ($user->hasAnyRole(['Admin', 'Principal'])) {
            return route('dashboard');
        }
        if ($user->hasRole('Teacher')) {
            return route('teacher.dashboard');
        }
        if ($user->hasAnyRole(['Student','Parent'])) return route('portal.home');
        abort(403, 'Your account does not have panel access.');
    }
}

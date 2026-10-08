<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class AttendancePolicy
{
    public static function admin(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Principal']);
    }

    public function mark(User $user, SchoolClass $class, int $section, int $session, ?string $date = null): bool
    {
        if (self::admin($user)) {
            return true;
        }

        return app(\App\Services\FirstPeriodAttendance::class)->allows($user, $class->id, $section, $session, $date);
    }
}

<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;

class PortalStudentResolver
{
    /**
     * Resolve the verified student for the current request context.
     * Never accepts student_id from request body or query string.
     */
    public static function resolveStudent(?Request $request = null): ?Student
    {
        $request = $request ?? request();
        return $request->attributes->get('portal_student');
    }

    /**
     * Resolve the verified student ID for the current request context.
     */
    public static function resolveStudentId(?Request $request = null): ?int
    {
        $request = $request ?? request();
        return $request->attributes->get('portal_student_id');
    }

    /**
     * Get the active portal role ('student' or 'parent').
     */
    public static function role(?Request $request = null): string
    {
        $request = $request ?? request();
        return (string) $request->attributes->get('portal_role', 'student');
    }
}

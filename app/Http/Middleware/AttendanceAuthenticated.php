<?php

namespace App\Http\Middleware;

use App\Policies\AttendancePolicy;
use Closure;
use Illuminate\Http\Request;

class AttendanceAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()) {
            return $request->expectsJson() ? response()->json(['message' => 'Sign in to manage attendance.'], 401) : redirect()->guest(route('login'));
        }
        abort_unless(AttendancePolicy::admin($request->user()) || strtolower($request->user()->role) === 'teacher', 403);

        return $next($request);
    }
}

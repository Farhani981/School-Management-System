<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FeesAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()) {
            return $request->expectsJson() ? response()->json(['message' => 'Sign in to manage fees.'], 401) : redirect()->guest(route('login'));
        }

        return $next($request);
    }
}

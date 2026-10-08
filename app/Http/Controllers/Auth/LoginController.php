<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\LoginDestination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function index()
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request)
    {
        // One limit per client IP also prevents bypassing the limit with different emails.
        $key = 'panel-login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        RateLimiter::hit($key, 60);
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Invalid email or password.']);
        }
        if (! $request->user()->hasAnyRole(['Admin', 'Principal', 'Teacher','Student','Parent'])) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email' => 'An authorized school account is required.']);
        }
        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        RateLimiter::clear($key);
        return redirect()->to(LoginDestination::for($request->user()));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->forget('url.intended');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('status', 'Logged out successfully.');
    }
}

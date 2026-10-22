<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PortalAuthController extends Controller
{
    /**
     * Show dedicated Student / Parent login view.
     */
    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->hasAnyRole(['Student', 'Parent'])) {
                return redirect()->route('portal.home');
            }
        }

        $activeType = $request->query('type', 'student');
        if (! in_array($activeType, ['student', 'parent'])) {
            $activeType = 'student';
        }

        return view('portal.auth.login', compact('activeType'));
    }

    /**
     * Process Student / Parent login attempt.
     */
    public function login(Request $request)
    {
        $request->validate([
            'type' => 'required|in:student,parent',
            'identifier' => 'required|string|max:120',
            'password' => 'required|string|min:6',
        ]);

        $ip = $request->ip();
        $rateKey = 'portal-login:' . $ip;

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            throw ValidationException::withMessages([
                'identifier' => "Too many login attempts. Please wait {$seconds} seconds before trying again.",
            ]);
        }

        RateLimiter::hit($rateKey, 60);

        $type = $request->input('type');
        $identifier = trim($request->input('identifier'));
        $password = $request->input('password');
        $remember = $request->boolean('remember');

        $user = null;

        if ($type === 'student') {
            // Student logs in with Admission No or Roll No + Password
            $student = Student::with('user')
                ->where(function ($q) use ($identifier) {
                    $q->where('admission_no', $identifier)
                      ->orWhere('roll_number', $identifier);
                })
                ->first();

            if ($student && $student->user) {
                if (Hash::check($password, $student->user->password)) {
                    $user = $student->user;
                }
            } elseif ($student && ! $student->user) {
                // If student doesn't have an auth user attached, check if user exists by student email
                $matchedUser = User::where('email', $student->email)->first();
                if ($matchedUser && Hash::check($password, $matchedUser->password)) {
                    $student->user_id = $matchedUser->id;
                    $student->save();
                    $user = $matchedUser;
                }
            } else {
                // Fallback: check user by email or username who has student role
                $candidateUser = User::where('email', $identifier)->first();
                if ($candidateUser && $candidateUser->hasRole('Student') && Hash::check($password, $candidateUser->password)) {
                    $user = $candidateUser;
                }
            }

            if (! $user) {
                throw ValidationException::withMessages([
                    'identifier' => 'Invalid Admission No / Roll No or password.',
                ]);
            }

            // Ensure role assignment
            if (! $user->hasRole('Student')) {
                $user->assignRole('Student');
            }

            Auth::login($user, $remember);
            RateLimiter::clear($rateKey);

            $request->session()->regenerate();
            $request->session()->put('portal_role', 'student');
            $request->session()->forget('url.intended');

            return redirect()->route('portal.home')->with('status', 'Welcome back, ' . $user->name . '!');
        } else {
            // Parent logs in with phone or email + Password
            $candidateUser = User::where(function ($q) use ($identifier) {
                $q->where('email', $identifier)
                  ->orWhere('phone', $identifier);
            })->first();

            if (! $candidateUser || ! Hash::check($password, $candidateUser->password)) {
                throw ValidationException::withMessages([
                    'identifier' => 'Invalid Phone / Email or password.',
                ]);
            }

            // Ensure user has parent role
            if (! $candidateUser->hasRole('Parent')) {
                $candidateUser->assignRole('Parent');
            }

            $user = $candidateUser;
            Auth::login($user, $remember);
            RateLimiter::clear($rateKey);

            $request->session()->regenerate();
            $request->session()->put('portal_role', 'parent');
            $request->session()->forget('url.intended');

            // Default active student to parent's first child
            $firstChild = $user->children()->first();
            if ($firstChild) {
                $request->session()->put('active_student_id', $firstChild->id);
            }

            return redirect()->route('portal.home')->with('status', 'Welcome back, ' . $user->name . '!');
        }
    }

    /**
     * Parent switches active child (verified strictly against parent_student).
     */
    public function switchChild(Request $request)
    {
        $request->validate([
            'student_id' => 'required|integer',
        ]);

        $user = $request->user();
        if (! $user || ! $user->hasRole('Parent')) {
            abort(403, 'Only parents can switch children.');
        }

        // Verify that this student is actually linked to this parent
        $isLinked = $user->children()->where('students.id', $request->student_id)->exists();

        if (! $isLinked) {
            abort(403, 'Unauthorized student selection.');
        }

        session(['active_student_id' => (int) $request->student_id]);
        $student = Student::find($request->student_id);

        return back()->with('status', 'Switched active student to ' . ($student?->full_name ?? 'selected child') . '.');
    }

    /**
     * Show Change Password view.
     */
    public function showChangePassword()
    {
        return view('portal.auth.change-password');
    }

    /**
     * Process password change for portal user.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('status', 'Your password has been updated successfully.');
    }

    /**
     * Show Forgot Password view.
     */
    public function showForgotPassword()
    {
        return view('portal.auth.forgot-password');
    }

    /**
     * Basic Forgot Password handler.
     */
    public function sendResetInstructions(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
        ]);

        return back()->with('status', 'Password reset instructions have been forwarded to the school administrator. You may also contact the school office with your Admission/Phone details.');
    }

    /**
     * Portal logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login')->with('status', 'You have been signed out successfully.');
    }
}

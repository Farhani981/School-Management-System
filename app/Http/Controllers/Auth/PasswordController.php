<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function request() { return view('auth.forgot-password'); }

    public function email(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $status = Password::sendResetLink($data);
        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors(['email' => 'Please wait before requesting another reset link.'])->withInput();
        }
        // Same response for unknown addresses, so this form does not disclose accounts.
        return back()->with('status', 'If this email has an account, a password reset link has been sent.');
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string'], 'email' => ['required', 'email'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Password reset successfully. Please sign in.')
            : back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
    }
}

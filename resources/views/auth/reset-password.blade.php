@extends('layouts.guest')
@section('title','Reset Password')
@section('content')
<div class="auth-heading"><h2>RESET PASSWORD</h2><p>Choose a new password for your account.</p></div>
<form method="POST" action="{{ route('password.update') }}" class="auth-form">@csrf<input type="hidden" name="token" value="{{ $token }}">
<div class="auth-field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email',$email) }}" autocomplete="username" required autofocus>@error('email')<p class="auth-error" role="alert">{{ $message }}</p>@enderror</div>
<div class="auth-field"><label for="password">New password</label><input id="password" type="password" name="password" autocomplete="new-password" required minlength="8">@error('password')<p class="auth-error" role="alert">{{ $message }}</p>@enderror</div>
<div class="auth-field"><label for="password-confirmation">Confirm password</label><input id="password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" required minlength="8"></div>@error('token')<p class="auth-error" role="alert">{{ $message }}</p>@enderror
<button class="auth-submit" type="submit">RESET PASSWORD <span aria-hidden="true">→</span></button>
</form>
@endsection

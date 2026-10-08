@extends('layouts.guest')
@section('title','User Login')
@section('content')
<div class="auth-heading"><h2>USER LOGIN</h2><p>Sign in to your school account.</p></div>
<form method="POST" action="{{ route('login.store') }}" class="auth-form">@csrf
<div class="auth-field"><label for="email">Email address</label><div class="auth-input-wrap"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 6 9 7 9-7"/></svg><input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Enter your email" autocomplete="username" required autofocus maxlength="255" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror></div>@error('email')<p id="email-error" class="auth-error" role="alert">{{ $message }}</p>@enderror</div>
<div class="auth-field"><label for="password">Password</label><div class="auth-input-wrap"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><input id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror><button type="button" class="auth-password-toggle" data-password-toggle="password" aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>@error('password')<p id="password-error" class="auth-error" role="alert">{{ $message }}</p>@enderror</div>
<div class="auth-options"><label class="auth-remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Remember me</label><a href="{{ route('password.request') }}">Forgot password?</a></div>@error('remember')<p class="auth-error">{{ $message }}</p>@enderror
<button type="submit" class="auth-submit">LOGIN <span aria-hidden="true">→</span></button>
</form>
@endsection

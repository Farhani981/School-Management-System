@extends('layouts.guest')
@section('title','Forgot Password')
@section('content')
<div class="auth-heading"><h2>FORGOT PASSWORD?</h2><p>Enter your account email to request a reset link.</p></div>
<form method="POST" action="{{ route('password.email') }}" class="auth-form">@csrf
<div class="auth-field"><label for="email">Email address</label><div class="auth-input-wrap"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 6 9 7 9-7"/></svg><input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Enter your email" autocomplete="email" required autofocus maxlength="255" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror></div>@error('email')<p id="email-error" class="auth-error" role="alert">{{ $message }}</p>@enderror</div>
<button class="auth-submit" type="submit">SEND RESET LINK <span aria-hidden="true">→</span></button><a class="auth-back" href="{{ route('login') }}">← Back to login</a>
</form>
@endsection

@extends('layouts.guest')
@section('title','Student Registration')
@section('content')
<div class="auth-heading"><h2>STUDENT REGISTRATION</h2><p>Create your student portal account.</p></div>
<form method="POST" action="{{ route('register.store') }}" class="auth-form">@csrf
<div class="auth-field"><label for="name">Full name</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="120">@error('name')<p class="auth-error">{{ $message }}</p>@enderror</div>
<div class="auth-field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="255">@error('email')<p class="auth-error">{{ $message }}</p>@enderror</div>
<div class="auth-field"><label for="password">Password</label><input id="password" type="password" name="password" required minlength="8">@error('password')<p class="auth-error">{{ $message }}</p>@enderror</div>
<div class="auth-field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" required minlength="8"></div>
<button type="submit" class="auth-submit">REGISTER <span aria-hidden="true">→</span></button>
</form><p class="auth-register-link"><a href="{{ route('login') }}">Back to login</a></p>
@endsection

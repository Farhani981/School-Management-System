@extends('layouts.portal')
@section('title', 'Change Password')

@section('content')
<div class="portal-page-header">
    <div>
        <h1 class="portal-page-title">Change Password</h1>
        <p class="portal-page-subtitle">Update your portal account security credentials</p>
    </div>
</div>

<div style="max-width: 520px; background: white; border: 1px solid var(--portal-border); border-radius: var(--portal-radius); padding: 28px; box-shadow: var(--portal-shadow-sm);">
    <form method="POST" action="{{ route('portal.password.update') }}">
        @csrf
        
        <div style="margin-bottom: 20px;">
            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">Current Password</label>
            <input type="password" name="current_password" required style="width:100%; height:44px; padding:0 14px; border:1px solid var(--portal-border); border-radius:10px; outline:none; font-family:inherit; box-sizing:border-box;">
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">New Password</label>
            <input type="password" name="password" required minlength="8" style="width:100%; height:44px; padding:0 14px; border:1px solid var(--portal-border); border-radius:10px; outline:none; font-family:inherit; box-sizing:border-box;">
            <small style="color:var(--portal-muted); font-size:12px; margin-top:4px; display:block;">Must be at least 8 characters long.</small>
        </div>

        <div style="margin-bottom: 24px;">
            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">Confirm New Password</label>
            <input type="password" name="password_confirmation" required minlength="8" style="width:100%; height:44px; padding:0 14px; border:1px solid var(--portal-border); border-radius:10px; outline:none; font-family:inherit; box-sizing:border-box;">
        </div>

        <div style="display: flex; gap: 12px;">
            <button type="submit" class="portal-btn-primary">
                <x-icon name="check" size="16" />
                <span>Update Password</span>
            </button>
            <a href="{{ route('portal.home') }}" class="portal-btn-secondary">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection

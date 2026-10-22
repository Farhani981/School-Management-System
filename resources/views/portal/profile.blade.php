@extends('layouts.portal')
@section('title', 'My Profile')

@section('content')
<div class="portal-page-header">
    <div>
        <h1 class="portal-page-title">Profile Information</h1>
        <p class="portal-page-subtitle">Personal and academic records on file</p>
    </div>
    <div>
        <a href="{{ route('portal.password.change') }}" class="portal-btn-secondary">
            <x-icon name="lock" size="16" />
            <span>Change Password</span>
        </a>
    </div>
</div>

@if($student)
    <div style="display: grid; grid-template-columns: 280px 1fr; gap: 24px; align-items: start;">
        <!-- Avatar / Student Card -->
        <div style="background: white; border: 1px solid var(--portal-border); border-radius: var(--portal-radius); padding: 24px; text-align: center; box-shadow: var(--portal-shadow-sm);">
            <div style="width: 88px; height: 88px; border-radius: 50%; background: linear-gradient(135deg, var(--portal-primary), #6366F1); color: white; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 700; margin: 0 auto 16px;">
                {{ strtoupper(substr($student->first_name, 0, 1)) }}
            </div>
            <h3 style="margin: 0 0 4px; font-size: 18px; font-weight: 700;">{{ $student->full_name }}</h3>
            <p style="margin: 0 0 16px; color: var(--portal-muted); font-size: 13px;">
                Admission No: <strong>{{ $student->admission_no }}</strong>
            </p>
            <div style="display: inline-block;">
                <x-portal.status-badge variant="success">{{ $student->status ?? 'Active' }}</x-portal.status-badge>
            </div>
        </div>

        <!-- Academic & Personal Details -->
        <div style="background: white; border: 1px solid var(--portal-border); border-radius: var(--portal-radius); padding: 28px; box-shadow: var(--portal-shadow-sm);">
            <h4 style="margin: 0 0 20px; font-size: 16px; font-weight: 700; color: var(--portal-text); border-bottom: 1px solid var(--portal-border-subtle); padding-bottom: 12px;">
                Academic Details
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px;">
                <div>
                    <label style="font-size: 12px; color: var(--portal-muted); text-transform: uppercase; font-weight: 600;">Class & Section</label>
                    <div style="font-size: 14px; font-weight: 600; margin-top: 4px;">
                        Class {{ $student->schoolClass?->name ?? 'N/A' }} {{ $student->section?->name ? ' - '.$student->section->name : '' }}
                    </div>
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--portal-muted); text-transform: uppercase; font-weight: 600;">Roll Number</label>
                    <div style="font-size: 14px; font-weight: 600; margin-top: 4px;">{{ $student->roll_number ?? 'N/A' }}</div>
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--portal-muted); text-transform: uppercase; font-weight: 600;">Admission Date</label>
                    <div style="font-size: 14px; font-weight: 600; margin-top: 4px;">{{ $student->admission_date?->format('d M Y') ?? 'N/A' }}</div>
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--portal-muted); text-transform: uppercase; font-weight: 600;">Academic Session</label>
                    <div style="font-size: 14px; font-weight: 600; margin-top: 4px;">{{ $student->session?->name ?? 'Current' }}</div>
                </div>
            </div>

            <h4 style="margin: 0 0 20px; font-size: 16px; font-weight: 700; color: var(--portal-text); border-bottom: 1px solid var(--portal-border-subtle); padding-bottom: 12px;">
                Personal & Guardian Information
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div>
                    <label style="font-size: 12px; color: var(--portal-muted); text-transform: uppercase; font-weight: 600;">Father / Guardian</label>
                    <div style="font-size: 14px; font-weight: 600; margin-top: 4px;">{{ $student->father_name ?? $student->guardian_name ?? 'N/A' }}</div>
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--portal-muted); text-transform: uppercase; font-weight: 600;">Contact Phone</label>
                    <div style="font-size: 14px; font-weight: 600; margin-top: 4px;">{{ $student->phone ?? $student->guardian_phone ?? 'N/A' }}</div>
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--portal-muted); text-transform: uppercase; font-weight: 600;">Date of Birth</label>
                    <div style="font-size: 14px; font-weight: 600; margin-top: 4px;">{{ $student->date_of_birth?->format('d M Y') ?? 'N/A' }}</div>
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--portal-muted); text-transform: uppercase; font-weight: 600;">Gender</label>
                    <div style="font-size: 14px; font-weight: 600; margin-top: 4px;">{{ $student->gender ?? 'N/A' }}</div>
                </div>
                <div style="grid-column: 1 / -1;">
                    <label style="font-size: 12px; color: var(--portal-muted); text-transform: uppercase; font-weight: 600;">Address</label>
                    <div style="font-size: 14px; font-weight: 600; margin-top: 4px;">{{ $student->address ?? 'N/A' }}</div>
                </div>
            </div>
        </div>
    </div>
@else
    <x-portal.empty-state
        title="No Student Linked"
        description="Your user account is not currently linked to a registered student profile."
        icon="user"
    />
@endif
@endsection

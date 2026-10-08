@php
    $isTeacherOnly = auth()->user() && auth()->user()->hasRole('Teacher') && !auth()->user()->hasAnyRole(['Admin', 'Principal']);
    $layout = $isTeacherOnly ? 'layouts.teacher' : 'layouts.admin';
@endphp

@extends($layout)
@section('title', 'Student Details')

@section('content')
<div class="student-details-container max-w-7xl mx-auto pb-10">

    <!-- Print Header (Visible only when printed) -->
    <div class="hidden print-only mb-6 text-center border-b pb-4">
        <h1 class="text-2xl font-bold text-slate-800">School Management System</h1>
        <p class="text-sm text-slate-500">Official Student Profile Record</p>
    </div>

    <!-- PAGE HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 print-hide">
        <!-- Title & Breadcrumbs -->
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Student Details</h1>
            <nav class="flex items-center gap-1.5 text-xs text-slate-500 mt-1 font-medium" aria-label="Breadcrumb">
                <a href="{{ $isTeacherOnly ? route('teacher.dashboard') : route('dashboard') }}" class="hover:text-[#0A7EA4] transition-colors">Dashboard</a>
                <span class="text-slate-300">/</span>
                @if(!$isTeacherOnly)
                    <a href="{{ route('students.index') }}" class="hover:text-[#0A7EA4] transition-colors">Students</a>
                    <span class="text-slate-300">/</span>
                @else
                    <span class="text-slate-600">Students</span>
                    <span class="text-slate-300">/</span>
                @endif
                <span class="text-slate-700 font-semibold">Student Details</span>
            </nav>
        </div>

        {{-- Header Right Actions --}}
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Print Profile Button -->
            <button 
                type="button" 
                onclick="window.print()" 
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0A7EA4]/40 transition-all shadow-2xs"
            >
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>Print Profile</span>
            </button>

            @can('manageLogin', $student)
                {{-- Portal Login Action Button --}}
                @if($student->user_id && $student->user)
                    <button 
                        type="button" 
                        onclick="openLoginModal()" 
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg border border-[#0A7EA4] text-[#0A7EA4] bg-white hover:bg-[#e8f7fa] focus:outline-none focus:ring-2 focus:ring-[#0A7EA4]/40 transition-all shadow-2xs"
                    >
                        <svg class="w-3.5 h-3.5 text-[#0A7EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        <span>Login Details</span>
                    </button>
                @else
                    <button 
                        type="button" 
                        onclick="openLoginModal()" 
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg border border-[#0A7EA4] text-[#0A7EA4] bg-white hover:bg-[#e8f7fa] focus:outline-none focus:ring-2 focus:ring-[#0A7EA4]/40 transition-all shadow-2xs"
                    >
                        <svg class="w-3.5 h-3.5 text-[#0A7EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        <span>Create Login</span>
                    </button>
                @endif
            @endcan

            @if(!$isTeacherOnly)
                <!-- Certificates Dropdown -->
                <details class="action-dropdown relative">
                    <summary class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 cursor-pointer select-none">
                        <span>Certificates</span>
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </summary>
                    <div class="dropdown-menu">
                        @foreach(['leaving' => 'Leaving Certificate', 'character' => 'Character Certificate', 'bonafide' => 'Bonafide Certificate'] as $type => $label)
                            <a href="{{ route('students.certificate', [$student, $type]) }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </details>

                <!-- Back to Students -->
                <a href="{{ route('students.index') }}" class="inline-flex items-center gap-1 px-3 py-2 text-xs font-semibold rounded-lg text-slate-600 hover:text-slate-900 transition-colors">
                    <span>Back</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Notification Toast / Flash message -->
    @if(session('success'))
        <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">&times;</button>
        </div>
    @endif

    <!-- PROFILE CARD (White card, rounded, soft shadow, two columns with divider) -->
    <x-student.profile-card :student="$student" :options="$options" />

    <!-- TABS NAVIGATION (Teal text & 2px teal underline) -->
    <x-student.tab-nav :activeTab="'details'" :canViewFees="$canViewFees" />

    <!-- TAB 1: STUDENT DETAILS (Stacked white cards) -->
    <div id="tab-panel-details" class="student-tab-panel space-y-6" data-tab-panel="details">
        <span class="hidden" data-profile-tab="family" id="family"></span>
        <span class="hidden" data-profile-tab="documents" id="documents"></span>
        <span class="hidden" data-profile-tab="guardian" id="guardian"></span>
        <span class="hidden" data-profile-tab="results" id="results"></span>
        <span class="hidden" data-profile-tab="overview" id="overview"></span>

        <!-- 1. Parent / Guardian Detail (Full width) -->
        @include('students.partials.parent-guardian-card', ['student' => $student])

        <!-- 2. Two-column grid of cards: Previous School Details | Address -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Previous School Details Card -->
            <x-student.detail-card title="Previous School Details">
                <x-slot:icon>
                    <svg class="w-4 h-4 text-[#0A7EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </x-slot:icon>
                <div class="space-y-3.5 text-xs">
                    <div class="flex items-baseline gap-2">
                        <span class="font-bold text-slate-800 shrink-0 w-36">Previous School</span>
                        <span class="text-slate-400 font-medium select-none">:</span>
                        <span class="text-slate-600 font-medium flex-1">
                            {{ $student->previous_school ?: 'Not provided' }}
                        </span>
                    </div>

                    <div class="flex items-baseline gap-2">
                        <span class="font-bold text-slate-800 shrink-0 w-36">Last Class Completed</span>
                        <span class="text-slate-400 font-medium select-none">:</span>
                        <span class="text-slate-600 font-medium flex-1">
                            {{ $student->last_class ?: 'Not provided' }}
                        </span>
                    </div>

                    <div class="flex items-baseline gap-2">
                        <span class="font-bold text-slate-800 shrink-0 w-36">Reason for Leaving</span>
                        <span class="text-slate-400 font-medium select-none">:</span>
                        <span class="text-slate-600 font-medium flex-1">
                            {{ $student->leaving_reason ?: 'Not provided' }}
                        </span>
                    </div>
                </div>
            </x-student.detail-card>

            <!-- Address Card -->
            <x-student.detail-card title="Address">
                <x-slot:icon>
                    <svg class="w-4 h-4 text-[#0A7EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </x-slot:icon>
                <div class="space-y-4 text-xs">
                    <div>
                        <span class="font-bold text-slate-800 block mb-1">Current Address</span>
                        <p class="text-slate-600 leading-relaxed font-medium bg-slate-50/70 p-3 rounded-lg border border-slate-100">
                            {{ $student->address ?: 'Not provided' }}
                        </p>
                    </div>

                    <div>
                        <span class="font-bold text-slate-800 block mb-1">Permanent Address</span>
                        <p class="text-slate-600 leading-relaxed font-medium bg-slate-50/70 p-3 rounded-lg border border-slate-100">
                            {{ $student->permanent_address ?: 'Not provided' }}
                        </p>
                    </div>
                </div>
            </x-student.detail-card>
        </div>

        <!-- 3. Two-column grid of cards: Medical Details | Documents -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Medical Details Card -->
            <x-student.detail-card title="Medical Details">
                <x-slot:icon>
                    <svg class="w-4 h-4 text-[#0A7EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                </x-slot:icon>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs mb-4">
                    <div class="p-3 rounded-xl bg-slate-50/70 border border-slate-100 text-center">
                        <span class="text-slate-400 font-semibold block text-[11px] mb-1">Blood Group</span>
                        <span class="text-sm font-bold text-slate-800 font-mono">
                            {{ $student->blood_group ?: 'Not provided' }}
                        </span>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50/70 border border-slate-100 text-center">
                        <span class="text-slate-400 font-semibold block text-[11px] mb-1">Height</span>
                        <span class="text-sm font-bold text-slate-800">
                            {{ $student->height ?: 'Not provided' }}
                        </span>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50/70 border border-slate-100 text-center">
                        <span class="text-slate-400 font-semibold block text-[11px] mb-1">Weight</span>
                        <span class="text-sm font-bold text-slate-800">
                            {{ $student->weight ?: 'Not provided' }}
                        </span>
                    </div>
                </div>

                <div class="text-xs">
                    <span class="font-bold text-slate-800 block mb-1">Allergies / Conditions</span>
                    <p class="text-slate-600 font-medium bg-slate-50/70 p-3 rounded-lg border border-slate-100">
                        {{ $student->allergies ?: 'Not provided' }}
                    </p>
                </div>
            </x-student.detail-card>

            <!-- Documents Card (Hidden from Teachers) -->
            @if($canViewDocuments)
                @include('students.partials.documents-card', ['student' => $student, 'documents' => $documents])
            @else
                <x-student.detail-card title="Academic Status">
                    <div class="text-center py-6 text-xs text-slate-500">
                        <p class="font-medium">Student is actively enrolled in {{ $student->schoolClass?->name ?? 'Class' }} &bull; {{ $student->section?->name ?? 'Section' }}.</p>
                        <p class="text-[11px] text-slate-400 mt-1">Class Teacher Assignment Active</p>
                    </div>
                </x-student.detail-card>
            @endif
        </div>

        <!-- 4. Siblings Card (Shown only if siblings exist) -->
        @if($siblings && $siblings->isNotEmpty())
            <x-student.detail-card title="Siblings">
                <x-slot:icon>
                    <svg class="w-4 h-4 text-[#0A7EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </x-slot:icon>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5">
                    @foreach($siblings as $sibling)
                        @php
                            $sInitial = mb_strtoupper(mb_substr($sibling->first_name, 0, 1) . mb_substr($sibling->last_name ?? '', 0, 1)) ?: 'SB';
                        @endphp
                        <a 
                            href="{{ route('students.show', $sibling) }}" 
                            class="p-3.5 rounded-xl border border-slate-200/80 hover:border-[#0A7EA4] hover:shadow-md transition-all flex items-center gap-3 bg-white group"
                        >
                            <div class="w-10 h-10 rounded-full bg-[#e8f7fa] text-[#0A7EA4] flex items-center justify-center font-bold text-xs shrink-0 group-hover:scale-105 transition-transform">
                                {{ $sInitial }}
                            </div>
                            <div class="truncate">
                                <h5 class="text-xs font-bold text-slate-800 group-hover:text-[#0A7EA4] truncate">{{ $sibling->first_name }} {{ $sibling->last_name }}</h5>
                                <span class="text-[11px] text-slate-500 block truncate">{{ $sibling->schoolClass?->name ?? 'Class' }} - {{ $sibling->section?->name ?? 'Sec' }}</span>
                                <span class="text-[10px] text-slate-400 font-mono block">{{ $sibling->admission_no }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </x-student.detail-card>
        @endif

        <!-- 5. Description / Remarks Card (Full width) -->
        <x-student.detail-card title="Description / Remarks">
            <x-slot:icon>
                <svg class="w-4 h-4 text-[#0A7EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path></svg>
            </x-slot:icon>
            <div class="text-xs text-slate-700 leading-relaxed font-normal bg-slate-50/50 p-4 rounded-xl border border-slate-100">
                @if($student->remarks)
                    {{ $student->remarks }}
                @else
                    <span class="text-slate-400 italic">No additional description or remarks recorded for this student.</span>
                @endif
            </div>
        </x-student.detail-card>

        @if(!$isTeacherOnly)
            @include('students.partials.records')
        @endif
    </div>

    <!-- TAB 2: ATTENDANCE TAB -->
    <div id="tab-panel-attendance" class="student-tab-panel hidden" data-tab-panel="attendance">
        @include('students.partials.attendance-tab', ['student' => $student, 'attendanceProfile' => $attendanceProfile])
    </div>

    <!-- TAB 3: FEES TAB (Hidden from teachers & non-authorized users) -->
    @if($canViewFees)
        <div id="tab-panel-fees" class="student-tab-panel hidden" data-tab-panel="fees">
            @include('students.partials.fees-tab', ['student' => $student, 'feeData' => $feeData])
        </div>
    @endif

    <!-- TAB 5: PROGRESS TAB (Coming Soon) -->
    <div id="tab-panel-progress" class="student-tab-panel hidden" data-tab-panel="progress">
        @include('students.partials.coming-soon-tab', [
            'module' => 'Academic Progress',
            'description' => 'Performance analytics, quiz performance trends, co-curricular achievements, and behavior progress notes.'
        ])
    </div>
</div>

<!-- Status Change Confirmation Modal -->
@can('updateStatus', $student)
    @include('students.partials.status-modal', ['student' => $student])
@endcan

<!-- Portal Login Details / Create Modal -->
@can('manageLogin', $student)
    @include('students.partials.login-modal', ['student' => $student])
@endcan

<!-- Tab Switching Script -->
<script>
    function switchStudentTab(targetTab) {
        // Update URL hash without jumping
        if (history.pushState) {
            history.pushState(null, null, '#' + targetTab);
        } else {
            location.hash = '#' + targetTab;
        }

        // Update button states
        const buttons = document.querySelectorAll('.student-tab-button');
        buttons.forEach(btn => {
            const isTarget = btn.getAttribute('data-tab') === targetTab;
            if (isTarget) {
                btn.classList.add('border-[#0A7EA4]', 'text-[#0A7EA4]');
                btn.classList.remove('border-transparent', 'text-slate-500');
                btn.setAttribute('aria-selected', 'true');
                const icon = btn.querySelector('.tab-icon');
                if (icon) {
                    icon.classList.add('text-[#0A7EA4]');
                    icon.classList.remove('text-slate-400');
                }
            } else {
                btn.classList.remove('border-[#0A7EA4]', 'text-[#0A7EA4]');
                btn.classList.add('border-transparent', 'text-slate-500');
                btn.setAttribute('aria-selected', 'false');
                const icon = btn.querySelector('.tab-icon');
                if (icon) {
                    icon.classList.remove('text-[#0A7EA4]');
                    icon.classList.add('text-slate-400');
                }
            }
        });

        // Toggle panel visibility
        const panels = document.querySelectorAll('.student-tab-panel');
        panels.forEach(panel => {
            if (panel.getAttribute('data-tab-panel') === targetTab) {
                panel.classList.remove('hidden');
            } else {
                panel.classList.add('hidden');
            }
        });
    }

    // Support deep-linking via hash on page load
    document.addEventListener('DOMContentLoaded', () => {
        const hash = window.location.hash.replace('#', '');
        if (hash && document.getElementById('tab-panel-' + hash)) {
            switchStudentTab(hash);
        }
    });
</script>
@endsection

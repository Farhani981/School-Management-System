@extends('layouts.teacher')
@section('title','Teacher Dashboard')
@section('content')
@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $emptyRegisterMessage = collect($cards)->contains(fn ($card) => $card['can_mark_attendance']) ? 'Your first-period class has no active students to mark.' : 'No first-period attendance assigned to you today.';
    $markedTotal = $registerTotal - $pendingAttendance;
    $ringOffset = 314.159 * (1 - ($registerTotal ? $markedTotal / $registerTotal : 0));
    $rows = collect($registers);
    $assignedClasses = collect($cards)->unique(fn ($card) => $card['assignment']->class_id.':'.$card['assignment']->section_id.':'.$card['assignment']->session_id);
    if ($scheduleAvailable) {
        $rows = collect($periods)->map(function ($period) use ($cards, $registers) {
            $card = collect($cards)->first(fn ($item) => $item['assignment']->id === ($period['assignment_id'] ?? null));
            $register = $card ? collect($registers)->first(fn ($item) => $item['assignment']->class_id === $card['assignment']->class_id && $item['assignment']->section_id === $card['assignment']->section_id && $item['assignment']->session_id === $card['assignment']->session_id) : null;
            return $register ? array_merge($register, $period, ['assignment' => $card['assignment'], 'subjects' => $card['assignment']->subject?->name]) : $period;
        });
    }
    // TODO: enable these shortcuts only when teacher create routes and forms are implemented.
    $quickActions = collect([['teacher.notices.create','New notice'],['teacher.leaves.create','Apply leave']])->filter(fn ($action) => \Illuminate\Support\Facades\Route::has($action[0]));
@endphp
<div class="teacher-today" data-teacher-dashboard data-date="{{ $dashboardDate }}" data-timezone="{{ $dashboardTimezone }}" data-pending="{{ $pendingAttendance }}" data-total="{{ $registerTotal }}" data-empty-register-message="{{ $emptyRegisterMessage }}">
    <div class="td-page-heading"><div><p class="td-eyebrow">YOUR TEACHING DAY</p><h1>Teacher Dashboard</h1></div><time datetime="{{ $dashboardDate }}">{{ today()->format('l, d M Y') }}</time></div>
    <section class="td-hero" aria-label="Your day at a glance">
        <div class="td-hero-copy">
            @if($scheduleAvailable)<p class="td-greeting" data-greeting>{{ $greeting }}, {{ auth()->user()->name }}.</p><h2 data-day-headline>Today's classes</h2>
            @else<h2 data-greeting>{{ $greeting }}, {{ auth()->user()->name }}.</h2>@endif
            <p data-register-message>{{ !$registerTotal ? $emptyRegisterMessage : ($pendingAttendance ? $pendingAttendance.' '.\Illuminate\Support\Str::plural('register', $pendingAttendance).' '.($pendingAttendance === 1 ? 'is' : 'are').' waiting.' : 'All registers are marked.') }}</p>
            <button type="button" class="td-button td-hero-button" data-mark-first @if(!$pendingAttendance) hidden @endif><x-icon name="calendar" size="18"/> Mark attendance</button>
        </div>
        <div class="td-progress" role="progressbar" aria-label="Registers marked today" aria-valuemin="0" aria-valuemax="{{ $registerTotal ?: 1 }}" aria-valuenow="{{ $markedTotal }}">
            <svg viewBox="0 0 120 120" aria-hidden="true"><circle class="td-ring-track" cx="60" cy="60" r="50"/><circle class="td-ring-fill" cx="60" cy="60" r="50" stroke-dasharray="314.159" stroke-dashoffset="{{ $ringOffset }}"/></svg>
            <div><strong><span data-marked-total>{{ $markedTotal }}</span><span class="td-ring-denominator"> / {{ $registerTotal }}</span></strong><span>marked today</span></div>
        </div>
    </section>
    <div class="td-overview-stats" aria-label="Teaching summary">
        <a href="{{ route('teacher.classes.index') }}"><span class="td-overview-icon"><x-icon name="classes" size="20"/></span><div><strong>{{ $assignedClasses->count() }}</strong><span>Assigned classes</span></div></a>
        <a href="{{ route('teacher.timetable.index') }}"><span class="td-overview-icon"><x-icon name="clock" size="20"/></span><div><strong>{{ $scheduleAvailable ? collect($periods)->filter(fn ($period) => !empty($period['assignment_id']))->count() : '—' }}</strong><span>Teaching periods today</span></div></a>
        <a href="{{ route('teacher.attendance.index') }}"><span class="td-overview-icon"><x-icon name="calendar" size="20"/></span><div><strong data-pending-summary>{{ $pendingAttendance }}</strong><span>Attendance pending</span></div></a>
        <a href="{{ route('teacher.exams.index') }}"><span class="td-overview-icon"><x-icon name="clipboard" size="20"/></span><div><strong>{{ $examPapers->whereIn('marks_status',['not_started','draft','returned'])->count() }}</strong><span>Papers awaiting marks</span></div></a>
    </div>
    <section class="td-card td-school-routine" aria-label="School attendance responsibility">
        <div><h2>First-period attendance</h2><p>You take daily attendance only for the class where you teach the first school period.</p></div>
        <div class="td-responsibility-list">
            @forelse(collect($cards)->where('can_mark_attendance', true)->unique(fn ($card) => $card['assignment']->class_id.':'.$card['assignment']->section_id.':'.$card['assignment']->session_id) as $card)
                <a class="td-button" href="{{ route('teacher.attendance.show', $card['assignment']) }}">{{ $card['assignment']->schoolClass->name }} &middot; {{ $card['assignment']->section->name }} &middot; {{ $card['marked'] ? 'View / Edit Attendance' : 'Mark Attendance' }}</a>
            @empty
                <p>No first-period class scheduled today. Your teaching periods are shown below.</p>
            @endforelse
        </div>
    </section>
    @if($quickActions->isNotEmpty())<nav class="td-quick-actions" aria-label="Quick actions">@foreach($quickActions as [$route,$label])<a class="td-button" href="{{ route($route) }}"><span aria-hidden="true">+</span> {{ $label }}</a>@endforeach</nav>@endif
    <div class="td-columns">
        <section class="td-card td-classes" aria-labelledby="td-classes-heading">
            <div class="td-section-heading"><div><h2 id="td-classes-heading">{{ $scheduleAvailable ? "Today's timetable" : 'My classes today' }}</h2>@unless($scheduleAvailable)<p>Timetable not configured. Showing your assigned classes.</p>@endunless</div><a href="{{ route('teacher.classes.index') }}">All classes <span aria-hidden="true">&rarr;</span></a></div>
            @foreach($warnings as $warning)<p class="td-warning">{{ $warning }}</p>@endforeach
            <div class="td-class-list">
                @forelse($rows as $row)
                    @if(!isset($row['assignment']))
                    <article class="td-class-row td-free-period" data-period-start="{{ $row['starts_at'] }}" data-period-end="{{ $row['ends_at'] }}" data-period-label="{{ ($row['is_break'] ?? false) ? 'Break' : 'Free period' }}"><time>{{ \Carbon\Carbon::parse($row['starts_at'])->format('g:i A') }}–{{ \Carbon\Carbon::parse($row['ends_at'])->format('g:i A') }}</time><div><h3>{{ ($row['is_break'] ?? false) ? 'Break' : 'Free period' }}</h3><p>{{ $row['period_name'] ?? 'School period' }} &middot; {{ \Carbon\Carbon::parse($row['starts_at'])->diffInMinutes(\Carbon\Carbon::parse($row['ends_at'])) }} min</p></div></article>
                    @else
                    @php
                        $assignment = $row['assignment'];
                        $groupKey = $assignment->class_id.'-'.$assignment->section_id.'-'.$assignment->session_id;
                        $panelId = 'quick-mark-'.$loop->index;
                        $canTakeAttendance = $row['can_mark_attendance'] && (!$scheduleAvailable || ($row['is_first_period'] ?? true));
                    @endphp
                    <article class="td-class-row" data-register-key="{{ $groupKey }}" data-marked="{{ $row['marked'] ? 'true' : 'false' }}" data-student-count="{{ $row['count'] }}" @if($scheduleAvailable) data-period-start="{{ $row['starts_at'] }}" data-period-end="{{ $row['ends_at'] }}" data-period-label="{{ $assignment->schoolClass->name }}" @endif>
                        <div class="td-row-main">
                            @if($scheduleAvailable)<div class="td-period-time"><strong>{{ $row['period_name'] ?? 'Period' }}</strong><time>{{ \Carbon\Carbon::parse($row['starts_at'])->format('g:i A') }}</time><span>to {{ \Carbon\Carbon::parse($row['ends_at'])->format('g:i A') }}</span></div>@else<div class="td-class-icon"><x-icon name="book" size="22"/></div>@endif
                            <div class="td-class-details"><div class="td-class-title"><h3>{{ $assignment->schoolClass->name }} <span>&middot; {{ $assignment->section->name }}</span></h3><span class="td-now" hidden>Now</span>@if($row['is_class_teacher'])<span class="td-class-teacher">Class teacher</span>@endif</div><p>{{ $row['subjects'] }}</p><nav class="td-row-links" aria-label="{{ $assignment->schoolClass->name }} {{ $assignment->section->name }} shortcuts"><a href="{{ route('teacher.classes.quiz',$assignment) }}">Quizzes</a><a href="{{ route('teacher.classes.progress',$assignment) }}">Progress</a><a href="{{ route('teacher.classes.students',$assignment) }}">Students</a></nav></div>
                            <div class="td-row-actions">
                                @if($canTakeAttendance)
                                    <span class="td-first-period-label">First-period attendance</span>
                                    <span @class(['td-status','td-done'=>$row['marked'],'td-pending'=>!$row['marked'] && $row['count']]) data-register-status>@if($row['marked'])Marked &middot; {{ $row['present_count'] }}/{{ $row['count'] }} present @elseif(!$row['count'])No active students @else Not marked @endif</span><button type="button" class="td-button" data-quick-toggle aria-controls="{{ $panelId }}" aria-expanded="false" @disabled($row['marked'] || !$row['count'])>{{ $row['marked'] ? 'Marked' : 'Quick mark' }}</button>
                                @else
                                    <span class="td-status">Teaching period</span>
                                @endif
                                @if(!empty($row['room']))<small>Room: {{ $row['room'] }}</small>@endif
                            </div>
                        </div>
                        @if($canTakeAttendance && !$row['marked'] && $row['count'])
                        <form class="td-quick-panel" id="{{ $panelId }}" method="POST" action="{{ route('teacher.attendance.store',$assignment) }}" hidden>
                            @csrf<input type="hidden" name="date" value="{{ $dashboardDate }}">
                            <div class="td-quick-heading"><div><h4>Mark attendance</h4><p>{{ $assignment->schoolClass->name }} &middot; Section {{ $assignment->section->name }} &middot; {{ $assignment->session->name }}</p></div><button type="button" class="td-button" data-all-present>All present</button></div>
                            <div class="td-student-list" tabindex="0" role="region" aria-label="Student attendance for {{ $assignment->schoolClass->name }} {{ $assignment->section->name }}">
                                @foreach($row['students'] as $student)
                                <fieldset class="td-student-row" data-student-id="{{ $student->id }}"><legend class="sr-only">Attendance for {{ $student->first_name }} {{ $student->last_name }}</legend><div class="td-student-name"><strong>{{ $student->first_name }} {{ $student->last_name }}</strong><span>Roll {{ $student->roll_number ?: '-' }}</span></div><input type="hidden" name="rows[{{ $loop->index }}][student_id]" value="{{ $student->id }}"><div class="td-segmented">@foreach(['present'=>'Present','absent'=>'Absent','late'=>'Late'] as $status=>$label)<label><input type="radio" name="rows[{{ $loop->parent->index }}][status]" value="{{ $status }}" @checked(($row['attendance'][$student->id]->status ?? null)===$status) required><span>{{ $label }}</span></label>@endforeach</div></fieldset>
                                @endforeach
                            </div>
                            <div class="td-quick-footer"><p data-quick-summary aria-live="polite">Choose a status for every student.</p><div><button type="button" class="td-button" data-quick-cancel>Cancel</button><button type="submit" class="td-button td-primary" data-quick-save disabled>Save attendance</button></div></div><p class="td-quick-error" role="alert" hidden></p>
                        </form>
                        @endif
                    </article>
                    @endif
                @empty<div class="td-empty"><x-icon name="classes" size="24"/><h3>No classes assigned yet.</h3><p>Your assigned classes will appear here.</p></div>@endforelse
            </div>
            <noscript><p class="td-warning">Open <a href="{{ route('teacher.attendance.index') }}">Attendance</a> to mark registers.</p></noscript>
        </section>
        <aside class="td-side">
            <section class="td-card" aria-labelledby="td-needs-heading"><h2 id="td-needs-heading">Needs you</h2><div class="td-needs-list"><a class="td-need" href="{{ route('teacher.attendance.index') }}" data-unmarked-need @if(!$pendingAttendance) hidden @endif><span class="td-need-icon"><x-icon name="calendar" size="18"/></span><span><strong data-unmarked-label>{{ $pendingAttendance }} {{ \Illuminate\Support\Str::plural('register', $pendingAttendance) }} not marked</strong><small>Complete today's attendance</small></span><span aria-hidden="true">&rarr;</span></a><a data-low-need @if(!$lowAttendanceCount) hidden @endif class="td-need" href="{{ route('teacher.progress.index', ['tab' => 'class', 'filter' => 'attention']) }}"><span class="td-need-icon"><x-icon name="users" size="18"/></span><span><strong data-low-label>{{ $lowAttendanceCount }} {{ \Illuminate\Support\Str::plural('student',$lowAttendanceCount) }} below 75% attendance</strong><small>Review in Student Progress</small></span><span aria-hidden="true">&rarr;</span></a> @if(isset($overdueNotesCount) && $overdueNotesCount > 0)<a class="td-need" href="{{ route('teacher.progress.index', ['tab' => 'class', 'filter' => 'attention']) }}" data-overdue-need><span class="td-need-icon"><x-icon name="clipboard" size="18"/></span><span><strong data-overdue-label>{{ $overdueNotesCount }} overdue {{ \Illuminate\Support\Str::plural('follow-up', $overdueNotesCount) }}</strong><small>Review in Student Progress</small></span><span aria-hidden="true">&rarr;</span></a>@endif<div class="td-all-clear" data-all-clear @if($pendingAttendance || $lowAttendanceCount || ($pendingQuizMarks ?? 0) || ($overdueNotesCount ?? 0)) hidden @endif><x-icon name="check" size="18"/> Nothing needs you right now</div></div></section>
            <section class="td-card td-notice" aria-labelledby="td-notice-heading"><div class="td-section-heading"><h2 id="td-notice-heading">Latest notice</h2><a href="{{ route('teacher.notices.index') }}">View all</a></div>@if($latestNotice)<a class="td-notice-title" href="{{ route('teacher.notices.index') }}" title="{{ $latestNotice->title }}">{{ $latestNotice->title }}</a><p><time datetime="{{ $latestNotice->date->toDateString() }}">{{ $latestNotice->date->format('d M') }}</time> &middot; School {{ $latestNotice->type }}</p>@else<p>No notices available.</p>@endif{{-- TODO: unread dot needs a real per-teacher notice read receipt. --}}</section>
        </aside>
    </div>
    @include('teacher.exams.summary')
    <p class="sr-only" data-dashboard-status role="status" aria-live="polite"></p>
</div>
@endsection

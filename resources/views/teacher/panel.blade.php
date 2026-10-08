@extends('layouts.teacher')
@section('title',$title)
@section('content')
@include('teacher.partials.header',['heading'=>$title, 'description' => $page === 'profile' ? 'Your personal details, teaching role and school account.' : ($page === 'leaves' ? 'Your leave requests, dates and approval history.' : 'Your classes and students, in one place.')])
<div @class(['panel student-form' => !in_array($page,['profile','leaves']), 'teacher-profile-page' => $page === 'profile', 'teacher-leave-page' => $page === 'leaves'])>
@if($page==='profile')
@include('teacher.partials.profile')
@elseif($page==='notices')
@include('teacher.partials.notices')
@elseif($page==='leaves')
@include('teacher.partials.leaves')
@elseif($page==='progress')
<div class="student-progress-module">
    <div class="progress-student-selector-bar">
        <form method="GET" action="{{ route('teacher.progress.index') }}" class="progress-select-form">
            <label for="progress-student-select" class="form-label">Select Student:</label>
            <select id="progress-student-select" name="student_id" onchange="this.form.submit()">
                @forelse($students as $st)
                    <option value="{{ $st->id }}" @selected(($selectedStudent?->id ?? 0) === $st->id)>
                        {{ $st->schoolClass->name }} · {{ $st->section->name }} &mdash; Roll #{{ $st->roll_number }}: {{ $st->full_name }}
                    </option>
                @empty
                    <option value="">No active students found in your assigned classes</option>
                @endforelse
            </select>
        </form>

        @if($selectedStudent)
            <div class="selected-student-chip">
                <span class="chip-avatar"><x-icon name="users" size="14" /></span>
                <div>
                    <strong>{{ $selectedStudent->full_name }}</strong>
                    <span>Admission #{{ $selectedStudent->admission_no }} &middot; Roll #{{ $selectedStudent->roll_number }}</span>
                </div>
            </div>
        @endif
    </div>

    @if($selectedStudent)
        @php
            $presentResults = $quizResults->filter(fn($r) => $r->attendance_status === 'present' && $r->percentage !== null);
            $avgPct = $presentResults->isNotEmpty() ? round($presentResults->avg('percentage'), 1) : 0;
            $passedCount = $presentResults->filter(fn($r) => $r->is_passed)->count();
        @endphp

        <!-- Performance Summary & Trend Line -->
        <div class="student-progress-summary-card">
            <div class="summary-stat-row">
                <div class="progress-mini-stat">
                    <span class="stat-lbl">ASSESSMENTS</span>
                    <strong class="stat-num">{{ $quizResults->count() }}</strong>
                </div>
                <div class="progress-mini-stat">
                    <span class="stat-lbl">ACADEMIC AVERAGE</span>
                    <strong class="stat-num text-teal">{{ $avgPct }}%</strong>
                </div>
                <div class="progress-mini-stat">
                    <span class="stat-lbl">PASSED</span>
                    <strong class="stat-num text-ok">{{ $passedCount }} / {{ $presentResults->count() }}</strong>
                </div>
            </div>

            <!-- Mini SVG Trend Line -->
            @if($presentResults->count() >= 2)
                @php
                    $points = $presentResults->values();
                    $w = 340; $h = 50; $pad = 10;
                    $step = ($w - 2 * $pad) / max(1, $points->count() - 1);
                    $pathD = '';
                    $circles = [];
                    foreach ($points as $idx => $pt) {
                        $x = $pad + ($idx * $step);
                        $y = $h - $pad - (($pt->percentage / 100) * ($h - 2 * $pad));
                        $pathD .= ($idx === 0 ? "M {$x},{$y}" : " L {$x},{$y}");
                        $circles[] = ['x' => $x, 'y' => $y, 'pct' => $pt->percentage, 'title' => $pt->quiz->title];
                    }
                @endphp
                <div class="progress-trend-box">
                    <span class="trend-title">Score Progression Trend:</span>
                    <svg viewBox="0 0 {{ $w }} {{ $h }}" class="progress-trend-svg" aria-label="Score progression trend line">
                        <line x1="{{ $pad }}" y1="{{ $h - $pad }}" x2="{{ $w - $pad }}" y2="{{ $h - $pad }}" stroke="var(--line)" stroke-width="1" stroke-dasharray="2" />
                        <path d="{{ $pathD }}" fill="none" stroke="var(--teal)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                        @foreach($circles as $c)
                            <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="3.5" fill="var(--card)" stroke="var(--teal)" stroke-width="2">
                                <title>{{ $c['title'] }}: {{ $c['pct'] }}%</title>
                            </circle>
                        @endforeach
                    </svg>
                </div>
            @endif
        </div>

        <!-- Student Quiz History Table -->
        <div class="student-quiz-history-card">
            <h3>Assessment History</h3>
            <div class="overflow-x-auto">
                <table class="student-quiz-table">
                    <thead>
                        <tr>
                            <th>Assessment Title</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th class="text-center">Score</th>
                            <th class="text-center">Percentage</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($quizResults as $res)
                        <tr>
                            <td>
                                <a href="{{ route('teacher.quizzes.show', $res->quiz) }}" class="quiz-title-link">
                                    {{ $res->quiz->title }}
                                </a>
                                <span class="text-muted d-block" style="font-size: 12px;">{{ $res->quiz->typeLabel() }}</span>
                            </td>
                            <td>{{ $res->quiz->subject->name }}</td>
                            <td>{{ $res->quiz->date->format('d M Y') }}</td>
                            <td class="text-center">
                                @if($res->marks_obtained !== null)
                                    <strong>{{ (float) $res->marks_obtained }}</strong> / {{ (float) $res->quiz->total_marks }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($res->percentage !== null)
                                    <strong>{{ $res->percentage }}%</strong>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge badge-neutral">{{ $res->grade }}</span>
                            </td>
                            <td class="text-center">
                                @if($res->attendance_status === 'present')
                                    @if($res->is_passed)
                                        <span class="badge badge-ok">Pass</span>
                                    @else
                                        <span class="badge badge-warn">Fail</span>
                                    @endif
                                @else
                                    <span class="badge badge-muted">{{ ucfirst($res->attendance_status) }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 24px;">
                                No assessment results recorded for this student yet.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@elseif($page==='timetable')
<div class="teacher-timetable-module">
    @if(empty($timetableData) || empty($timetableData['periods']) || $timetableData['periods']->isEmpty())
        <div class="panel" style="padding: 40px; text-align: center; border-radius: 18px; border: 1px dashed var(--line); background: var(--card);">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: var(--soft); color: var(--teal); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                <x-icon name="clock" size="24" />
            </div>
            <h3 style="font-family: 'Segoe UI', Arial, sans-serif; font-size: 18px; margin-bottom: 8px;">No Timetable Configured</h3>
            <p style="color: var(--mute); max-width: 460px; margin: 0 auto; font-size: 14px;">The administration has not yet configured or published period schedules. Please check back later or contact the academic office.</p>
        </div>
    @else
        @include('teacher.partials.daily-timetable')
    @endif
</div>
@else
<p>{{ $title }} will be available in the next phase.</p>
@endif
</div>
@endsection

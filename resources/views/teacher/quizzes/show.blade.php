@extends('layouts.teacher')
@section('title', 'Results: ' . $quiz->title)

@section('content')
<div class="quizzes-module print-clean-area">
    <!-- Header with Action Buttons -->
    <div class="quizzes-header-bar print-hide">
        <div>
            <div class="quiz-form-breadcrumb">
                <a href="{{ route('teacher.quizzes.index') }}">&larr; Back to Quizzes</a>
            </div>
            <h1 class="quizzes-title">Results: {{ $quiz->title }}</h1>
            <p class="quizzes-subtitle">
                {{ $quiz->schoolClass->name }} · {{ $quiz->section->name }} &middot;
                <strong>{{ $quiz->subject->name }}</strong> &middot;
                {{ $quiz->typeLabel() }} &middot;
                Conducted: {{ $quiz->date->format('d M Y') }} &middot;
                Total: <strong>{{ (float) $quiz->total_marks }}</strong> &middot;
                Pass: <strong>{{ (float) $quiz->passing_marks }}</strong>
            </p>
        </div>

        <div class="quizzes-header-actions">
            <a href="{{ route('teacher.quizzes.marks', $quiz) }}" class="button-secondary">
                <x-icon name="clipboard" size="14" />
                <span>{{ $quiz->computedStatus() === 'completed' ? 'Edit Marks' : 'Enter Marks' }}</span>
            </a>

            <a href="{{ route('teacher.quizzes.export', [$quiz, 'csv']) }}" class="button-secondary" title="Download Excel/CSV sheet">
                <x-icon name="arrow" size="14" /> CSV Export
            </a>

            <a href="{{ route('teacher.quizzes.export', [$quiz, 'pdf']) }}" class="button-primary" target="_blank" title="Printable PDF result report">
                <x-icon name="arrow" size="14" /> Download PDF
            </a>

            <button type="button" class="button-secondary btn-print-page" onclick="window.print()" title="Print this result page directly">
                Print
            </button>
        </div>
    </div>

    <!-- Print Header Only (Hidden on Screen) -->
    <div class="quiz-print-header">
        <h2>{{ config('app.name', 'School Management System') }}</h2>
        <h3>Assessment Result Sheet: {{ $quiz->title }}</h3>
        <p>{{ $quiz->schoolClass->name }} · {{ $quiz->section->name }} | {{ $quiz->subject->name }} | Date: {{ $quiz->date->format('d M Y') }} | Teacher: {{ $quiz->teacher?->name }}</p>
    </div>

    <!-- 6 Stat Cards Grid -->
    <div class="quiz-stats-grid">
        <!-- 1. Enrolled -->
        <div class="stat-box">
            <span class="stat-box-lbl">ENROLLED</span>
            <div class="stat-box-main">
                <span class="stat-box-num">{{ $stats['total_students'] }}</span>
                <span class="stat-box-unit">Students</span>
            </div>
            <span class="stat-box-note">{{ $stats['unmarked_count'] }} unmarked</span>
        </div>

        <!-- 2. Appeared -->
        <div class="stat-box">
            <span class="stat-box-lbl">APPEARED</span>
            <div class="stat-box-main">
                <span class="stat-box-num text-teal">{{ $stats['appeared_count'] }}</span>
                <span class="stat-box-unit">Present</span>
            </div>
            <span class="stat-box-note">{{ $stats['absent_count'] }} Absent · {{ $stats['exempt_count'] }} Exempt</span>
        </div>

        <!-- 3. Class Average -->
        <div class="stat-box">
            <span class="stat-box-lbl">AVERAGE</span>
            <div class="stat-box-main">
                <span class="stat-box-num">{{ $stats['average_marks'] }}</span>
                <span class="stat-box-unit">/ {{ (float) $quiz->total_marks }}</span>
            </div>
            <span class="stat-box-note">{{ $stats['average_percentage'] }}% of total</span>
        </div>

        <!-- 4. Highest Mark -->
        <div class="stat-box">
            <span class="stat-box-lbl">HIGHEST MARK</span>
            <div class="stat-box-main">
                <span class="stat-box-num text-ok">{{ $stats['highest_mark'] }}</span>
                <span class="stat-box-unit">pts</span>
            </div>
            <span class="stat-box-note">{{ (float)$quiz->total_marks > 0 ? round(($stats['highest_mark'] / (float)$quiz->total_marks)*100) : 0 }}% score</span>
        </div>

        <!-- 5. Lowest Mark -->
        <div class="stat-box">
            <span class="stat-box-lbl">LOWEST MARK</span>
            <div class="stat-box-main">
                <span class="stat-box-num">{{ $stats['lowest_mark'] }}</span>
                <span class="stat-box-unit">pts</span>
            </div>
            <span class="stat-box-note">{{ (float)$quiz->total_marks > 0 ? round(($stats['lowest_mark'] / (float)$quiz->total_marks)*100) : 0 }}% score</span>
        </div>

        <!-- 6. Pass Rate -->
        <div class="stat-box">
            <span class="stat-box-lbl">PASS RATE</span>
            <div class="stat-box-main">
                <span class="stat-box-num text-ok">{{ $stats['pass_percentage'] }}%</span>
            </div>
            <span class="stat-box-note">{{ $stats['passed_count'] }} Passed · {{ $stats['failed_count'] }} Failed</span>
        </div>
    </div>

    <!-- Marks Distribution Chart & Needs Attention Row -->
    <div class="quiz-analytics-row">
        <!-- Distribution Bar Chart -->
        <div class="quiz-chart-card">
            <div class="card-head-simple">
                <h3>Marks Distribution</h3>
                <span class="chart-subtitle">Frequency of student scores by percentage band</span>
            </div>

            @php
                $maxBucket = max(1, ...array_values($stats['distribution']));
            @endphp
            <div class="distribution-bars-wrap" aria-label="Marks distribution chart">
                @foreach($stats['distribution'] as $band => $count)
                    @php
                        $heightPct = min(100, max(6, round(($count / $maxBucket) * 100)));
                    @endphp
                    <div class="dist-col">
                        <div class="dist-bar-track">
                            <span class="dist-count-tag" style="bottom: {{ $heightPct + 2 }}%;">{{ $count }}</span>
                            <div class="dist-bar-fill" style="height: {{ $heightPct }}%;" title="{{ $band }}%: {{ $count }} students"></div>
                        </div>
                        <span class="dist-label">{{ $band }}%</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Needs Attention Section -->
        <div class="quiz-attention-card">
            <div class="card-head-simple">
                <h3>Needs Attention</h3>
                <span class="chart-subtitle">Students below passing mark or significantly below class average</span>
            </div>

            <div class="attention-list">
                @forelse($needsAttention as $item)
                    <div class="attention-item">
                        <div class="attention-info">
                            <strong>{{ $item['student']?->full_name }}</strong>
                            <span>Roll #{{ $item['student']?->roll_number }} &middot; Score: <strong class="text-warn">{{ $item['marks'] }} / {{ (float) $quiz->total_marks }}</strong> ({{ $item['percentage'] }}%)</span>
                        </div>
                        <div class="attention-action">
                            <a href="{{ route('teacher.progress.index', ['student' => $item['student']?->id]) }}" class="button-secondary btn-sm" title="Review student in progress history">
                                View Progress
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="attention-empty">
                        <x-icon name="check" size="20" class="text-ok" />
                        <span>All students performed above the intervention threshold!</span>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Ranked Student Results Table -->
    <div class="quiz-ranked-card">
        <div class="card-head-simple">
            <div>
                <h3>Student Results &amp; Ranking</h3>
                <span class="chart-subtitle">Ranked by marks obtained. Equal scores share the same rank. Absent and exempt students are excluded from rank.</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="quiz-ranked-table">
                <thead>
                    <tr>
                        <th style="width: 70px;" class="text-center">Rank</th>
                        <th style="width: 80px;">Roll #</th>
                        <th style="min-width: 200px;">Student Name</th>
                        <th style="width: 140px;" class="text-center">Marks Obtained</th>
                        <th style="width: 110px;" class="text-center">Percentage</th>
                        <th style="width: 80px;" class="text-center">Grade</th>
                        <th style="width: 120px;" class="text-center">Status</th>
                        <th style="min-width: 160px;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rankedStudents as $row)
                    @php
                        $isTopThree = is_numeric($row['rank']) && $row['rank'] <= 3;
                    @endphp
                    <tr class="ranked-row {{ $isTopThree ? 'row-top-rank' : '' }}">
                        <!-- Rank -->
                        <td class="text-center">
                            @if($row['rank'] === 1)
                                <span class="rank-badge rank-gold" title="1st Place">1</span>
                            @elseif($row['rank'] === 2)
                                <span class="rank-badge rank-silver" title="2nd Place">2</span>
                            @elseif($row['rank'] === 3)
                                <span class="rank-badge rank-bronze" title="3rd Place">3</span>
                            @elseif($row['rank'] !== '—')
                                <span class="rank-badge rank-standard">{{ $row['rank'] }}</span>
                            @else
                                <span class="rank-badge rank-none">—</span>
                            @endif
                        </td>

                        <!-- Roll # -->
                        <td>
                            <strong>{{ $row['student']?->roll_number ?: '—' }}</strong>
                        </td>

                        <!-- Student Name -->
                        <td>
                            <div class="ranked-student-info">
                                <a href="{{ route('teacher.progress.index', ['student' => $row['student']?->id]) }}" class="student-name hover-teal">{{ $row['student']?->full_name }}</a>
                                <span class="student-admission">{{ $row['student']?->admission_no }}</span>
                            </div>
                        </td>

                        <!-- Marks -->
                        <td class="text-center">
                            @if($row['marks'] !== null)
                                <strong class="marks-val">{{ $row['marks'] }}</strong>
                                <span class="marks-max">/ {{ (float) $quiz->total_marks }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        <!-- Percentage -->
                        <td class="text-center">
                            @if($row['percentage'] !== null)
                                <span class="pct-val">{{ $row['percentage'] }}%</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        <!-- Grade -->
                        <td class="text-center">
                            <span class="grade-pill grade-{{ strtolower(str_replace('+', 'plus', $row['grade'])) }}">
                                {{ $row['grade'] }}
                            </span>
                        </td>

                        <!-- Pass/Fail Badge -->
                        <td class="text-center">
                            @if($row['status'] === 'present')
                                @if($row['passed'])
                                    <span class="badge badge-ok"><span class="my-status-dot dot-ok"></span> Pass</span>
                                @else
                                    <span class="badge badge-warn"><span class="my-status-dot dot-warn"></span> Fail</span>
                                @endif
                            @elseif($row['status'] === 'absent')
                                <span class="badge badge-muted">Absent</span>
                            @elseif($row['status'] === 'exempt')
                                <span class="badge badge-neutral">Exempt</span>
                            @else
                                <span class="badge badge-neutral">Unmarked</span>
                            @endif
                        </td>

                        <!-- Remarks -->
                        <td>
                            <span class="remarks-text">{{ $row['remarks'] ?: '—' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center" style="padding: 30px;">
                            No student results recorded yet for this assessment.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

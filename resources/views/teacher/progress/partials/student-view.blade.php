@if(!$selectedStudent || !$studentData)
    <div class="quizzes-table-card text-center p-8">
        <x-icon name="users" size="36" class="text-mute mx-auto mb-3" />
        <h2 class="text-base font-bold text-ink">No Students Found</h2>
        <p class="text-sm text-mute">There are no active students in this section.</p>
    </div>
@else
    @php
        $st = $studentData['student'];
        $sum = $studentData['summary'];
        $status = $studentData['status'];
        $nav = $studentData['nav'];
        $trendData = $studentData['trend_chart'];
        $att = $studentData['attendance'];
    @endphp

    <!-- 1. Searchable Student Selector & Prev/Next Bar -->
    <section class="progress-student-bar card" aria-label="Student Selection">
        <div class="student-select-cluster">
            <label for="student-search-input" class="sr-only">Search Student</label>
            <div class="student-search-wrap">
                <!-- <x-icon name="search" size="16" class="search-icon" /> -->
                <input type="text" id="student-search-input" class="student-search-field"
                       placeholder="Search student by name, roll no, or admission no..."
                       autocomplete="off" aria-label="Filter student list">
            </div>

            <div class="student-dropdown-wrap">
                <select id="student-picker" class="student-picker-select" onchange="window.location.href='{{ route('teacher.progress.index') }}?class_id={{ $st->class_id }}&section_id={{ $st->section_id }}&tab=student&student=' + this.value;">
                    @foreach($roster as $studentItem)
                        <option value="{{ $studentItem->id }}"
                                data-name="{{ strtolower($studentItem->full_name) }}"
                                data-roll="{{ $studentItem->roll_number }}"
                                data-admission="{{ strtolower($studentItem->admission_no) }}"
                                @selected($studentItem->id === $st->id)>
                            Roll #{{ $studentItem->roll_number }} &middot; {{ $studentItem->full_name }} ({{ $studentItem->admission_no }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <nav class="student-nav-buttons" aria-label="Student roll number navigation">
            @if($nav['prev'])
                <a href="{{ route('teacher.progress.index', ['class_id' => $st->class_id, 'section_id' => $st->section_id, 'tab' => 'student', 'student' => $nav['prev']->id]) }}"
                   class="button-secondary btn-nav-step" title="Previous: Roll #{{ $nav['prev']->roll_number }} {{ $nav['prev']->full_name }}">
                    &larr; Prev (Roll #{{ $nav['prev']->roll_number }})
                </a>
            @else
                <button type="button" class="button-secondary btn-nav-step disabled" disabled aria-disabled="true">&larr; Prev</button>
            @endif

            <span class="student-roster-count">{{ $nav['current_index'] }} of {{ $nav['total_students'] }}</span>

            @if($nav['next'])
                <a href="{{ route('teacher.progress.index', ['class_id' => $st->class_id, 'section_id' => $st->section_id, 'tab' => 'student', 'student' => $nav['next']->id]) }}"
                   class="button-secondary btn-nav-step" title="Next: Roll #{{ $nav['next']->roll_number }} {{ $nav['next']->full_name }}">
                    Next (Roll #{{ $nav['next']->roll_number }}) &rarr;
                </a>
            @else
                <button type="button" class="button-secondary btn-nav-step disabled" disabled aria-disabled="true">Next &rarr;</button>
            @endif
        </nav>
    </section>

    <!-- 2. Student Header Card -->
    <header class="progress-student-header card">
        <div class="student-header-left">
            <div class="student-avatar-ring">
                @if($st->photo_url)
                    <img src="{{ $st->photo_url }}" alt="{{ $st->full_name }}" class="student-avatar-img">
                @else
                    <div class="student-avatar-initials">
                        {{ strtoupper(substr($st->first_name, 0, 1) . substr($st->last_name, 0, 1)) }}
                    </div>
                @endif
            </div>

            <div class="student-header-meta">
                <div class="student-name-row">
                    <h2 class="student-display-name">{{ $st->full_name }}</h2>
                    <span class="badge {{ $status['badge_class'] }}" title="{{ $status['reason'] }}">
                        <span class="status-indicator-dot"></span> {{ $status['label'] }}
                    </span>
                    @if($studentData['overdue_notes_count'] > 0)
                        <span class="badge status-warn" title="{{ $studentData['overdue_notes_count'] }} follow-up note is overdue">
                            <x-icon name="bell" size="12" /> Overdue follow-up
                        </span>
                    @endif
                </div>

                <div class="student-details-pills">
                    <span><strong>Class:</strong> {{ $st->schoolClass?->name }} &middot; {{ $st->section?->name }}</span>
                    <span><strong>Roll No:</strong> #{{ $st->roll_number }}</span>
                    <span><strong>Admission No:</strong> {{ $st->admission_no }}</span>
                    @if($st->gender)<span><strong>Gender:</strong> {{ ucfirst($st->gender) }}</span>@endif
                </div>

                <p class="student-status-reason" role="note">
                    <x-icon name="clipboard" size="14" class="text-mute inline-icon" />
                    <span>{{ $status['reason'] }}</span>
                </p>
            </div>
        </div>

        <div class="student-header-actions">
            <button type="button" class="button-secondary btn-add-note" data-open-modal="note-modal">
                <x-icon name="clipboard" size="15" /> Add Note
            </button>
            <button type="button" class="button-primary btn-export-pdf" data-open-modal="pdf-comment-modal">
                <x-icon name="clipboard" size="15" /> Export Report (PDF)
            </button>
        </div>
    </header>

    <!-- 3. Summary Stat Row (Dividers, no nested bordered cards) -->
    <section class="progress-summary-strip card" aria-label="Student Academic & Attendance KPIs">
        <div class="summary-metric-item">
            <span class="metric-label" title="Total count of marked and finalized assessments">Assessments</span>
            <strong class="metric-number">{{ $sum['assessments_count'] }}</strong>
            <span class="metric-footnote">Evaluated offline</span>
        </div>

        <div class="summary-metric-divider" aria-hidden="true"></div>

        <div class="summary-metric-item">
            <span class="metric-label" title="Weighted academic average across all attended assessments">Academic Average</span>
            <strong class="metric-number @if($sum['academic_average'] !== null && $sum['academic_average'] < 40) text-danger @else text-teal @endif">
                {{ $sum['academic_average'] !== null ? $sum['academic_average'].'%' : '–' }}
            </strong>
            <span class="metric-footnote">Pass threshold 40%</span>
        </div>

        <div class="summary-metric-divider" aria-hidden="true"></div>

        <div class="summary-metric-item">
            <span class="metric-label" title="Assessments passed out of total assessments attended">Passed</span>
            <strong class="metric-number text-ok">
                {{ $sum['passed_count'] }} <span class="metric-fraction">/ {{ $sum['passed_total'] }}</span>
            </strong>
            <span class="metric-footnote">{{ $sum['passed_total'] > 0 ? round(($sum['passed_count'] / $sum['passed_total']) * 100) : 0 }}% pass rate</span>
        </div>

        <div class="summary-metric-divider" aria-hidden="true"></div>

        <div class="summary-metric-item">
            <span class="metric-label" title="Cumulative attendance rate for the current academic session">Attendance %</span>
            <strong class="metric-number @if($sum['attendance_pct'] !== null && $sum['attendance_pct'] < 75) text-danger @endif">
                {{ $sum['attendance_pct'] !== null ? $sum['attendance_pct'].'%' : '–' }}
            </strong>
            <span class="metric-footnote @if($sum['attendance_pct'] !== null && $sum['attendance_pct'] < 75) text-danger @endif">
                {{ $sum['attendance_pct'] !== null && $sum['attendance_pct'] < 75 ? 'Critical (Below 75%)' : 'Minimum 75%' }}
            </span>
        </div>

        <div class="summary-metric-divider" aria-hidden="true"></div>

        <div class="summary-metric-item">
            <span class="metric-label" title="Section standing derived from average assessment percentage">Class Rank</span>
            <strong class="metric-number text-ink">{{ $sum['class_rank'] }}</strong>
            <span class="metric-footnote">Ties share rank</span>
        </div>

        <div class="summary-metric-divider" aria-hidden="true"></div>

        <div class="summary-metric-item">
            <span class="metric-label" title="Score shift between latest assessment and previous assessment">Trend</span>
            <div class="trend-badge-display trend-{{ $sum['trend']['direction'] }}">
                @if($sum['trend']['direction'] === 'up')
                    <span class="trend-arrow">&uarr;</span>
                @elseif($sum['trend']['direction'] === 'down')
                    <span class="trend-arrow">&darr;</span>
                @else
                    <span class="trend-arrow">&harr;</span>
                @endif
                <strong>{{ $sum['trend']['label'] }}</strong>
            </div>
            <span class="metric-footnote">Flat boundary: &plusmn;2%</span>
        </div>
    </section>

    <!-- 4. Performance Trend & Subject Breakdown -->
    <section class="progress-charts-section card" aria-label="Performance Trend and Subject Analysis">
        <div class="charts-section-header">
            <div>
                <h3 class="section-title">Performance Trend & Subject Mastery</h3>
                <p class="section-subtitle">Visual score trajectory and subject strengths compared against section peers.</p>
            </div>

            <!-- Trend Filters Strip -->
            <form method="GET" action="{{ route('teacher.progress.index') }}" class="charts-filter-bar" id="trend-filter-form">
                <input type="hidden" name="class_id" value="{{ $st->class_id }}">
                <input type="hidden" name="section_id" value="{{ $st->section_id }}">
                <input type="hidden" name="tab" value="student">
                <input type="hidden" name="student" value="{{ $st->id }}">

                <div class="filter-item">
                    <label for="filter-timeframe" class="sr-only">Timeframe</label>
                    <select name="timeframe" id="filter-timeframe" onchange="this.form.submit()">
                        <option value="">All Assessments</option>
                        <option value="last5" @selected(request('timeframe') === 'last5')>Last 5 Assessments</option>
                        <option value="term" @selected(request('timeframe') === 'term')>This Term (90 Days)</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="filter-subject" class="sr-only">Subject</label>
                    <select name="subject_id" id="filter-subject" onchange="this.form.submit()">
                        <option value="">All Subjects</option>
                        @foreach($classData['subjects'] as $sub)
                            <option value="{{ $sub->id }}" @selected(request('subject_id') == $sub->id)>{{ $sub->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-item">
                    <label for="filter-type" class="sr-only">Assessment Type</label>
                    <select name="type" id="filter-type" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        @foreach(\App\Models\Quiz::TYPES as $key => $label)
                            <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if(request('timeframe') || request('subject_id') || request('type'))
                    <a href="{{ route('teacher.progress.index', ['class_id' => $st->class_id, 'section_id' => $st->section_id, 'tab' => 'student', 'student' => $st->id]) }}" class="button-secondary btn-clear-filters" title="Reset filters">
                        Clear
                    </a>
                @endif
            </form>
        </div>

        <div class="charts-grid-layout">
            <!-- Line Chart: Score progression vs Class Average -->
            <div class="trend-chart-panel">
                <div class="chart-panel-heading">
                    <h4>Score Trajectory Over Time</h4>
                    <div class="chart-legend-row" aria-hidden="true">
                        <span class="legend-item"><span class="legend-line line-student"></span> Student %</span>
                        <span class="legend-item"><span class="legend-line line-class"></span> Class Average %</span>
                    </div>
                </div>

                @if(count($trendData['points']) === 0)
                    <div class="chart-empty-state">
                        <x-icon name="chart" size="28" class="text-mute mb-2" />
                        <p class="empty-main">No assessments yet</p>
                        <p class="empty-sub">Once assessments are marked, score trends will appear here.</p>
                    </div>
                @else
                    @php
                        $pts = collect($trendData['points']);
                        $count = $pts->count();
                        $w = 560; $h = 220;
                        $padLeft = 45; $padRight = 25; $padTop = 20; $padBottom = 40;
                        $plotW = $w - $padLeft - $padRight;
                        $plotH = $h - $padTop - $padBottom;
                        $step = $count > 1 ? $plotW / ($count - 1) : $plotW / 2;

                        $studentPath = '';
                        $classPath = '';
                        $coords = [];

                        foreach($pts as $idx => $pt) {
                            $x = $count > 1 ? ($padLeft + ($idx * $step)) : ($padLeft + $step);
                            $yStudent = $padTop + $plotH - (($pt['percentage'] / 100) * $plotH);
                            $yClass = $pt['class_average'] !== null ? ($padTop + $plotH - (($pt['class_average'] / 100) * $plotH)) : null;

                            $studentPath .= ($idx === 0 ? "M {$x},{$yStudent}" : " L {$x},{$yStudent}");
                            if ($yClass !== null) {
                                $classPath .= ($classPath === '' ? "M {$x},{$yClass}" : " L {$x},{$yClass}");
                            }

                            $coords[] = [
                                'x' => $x,
                                'yStudent' => $yStudent,
                                'yClass' => $yClass,
                                'title' => $pt['title'],
                                'date' => \Carbon\Carbon::parse($pt['date'])->format('d M'),
                                'score' => $pt['score_display'],
                                'percentage' => $pt['percentage'],
                                'class_average' => $pt['class_average'],
                            ];
                        }
                    @endphp

                    <div class="svg-chart-wrapper" tabindex="0" role="region" aria-label="Student percentage trajectory chart">
                        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="trend-svg" aria-hidden="true">
                            <!-- Y-Axis Gridlines & Labels -->
                            @foreach([100, 75, 50, 25, 0] as $gridVal)
                                @php $gy = $padTop + $plotH - (($gridVal / 100) * $plotH); @endphp
                                <line x1="{{ $padLeft }}" y1="{{ $gy }}" x2="{{ $w - $padRight }}" y2="{{ $gy }}" class="grid-line" />
                                <text x="{{ $padLeft - 8 }}" y="{{ $gy + 4 }}" class="grid-label">{{ $gridVal }}%</text>
                            @endforeach

                            <!-- Minimum passing guideline (40%) -->
                            @php $passY = $padTop + $plotH - ((40 / 100) * $plotH); @endphp
                            <line x1="{{ $padLeft }}" y1="{{ $passY }}" x2="{{ $w - $padRight }}" y2="{{ $passY }}" class="pass-line" />
                            <text x="{{ $w - $padRight - 3 }}" y="{{ $passY - 4 }}" class="pass-label" text-anchor="end">Pass (40%)</text>

                            <!-- Class Average Line (Dashed) -->
                            @if($classPath)
                                <path d="{{ $classPath }}" class="chart-path path-class" fill="none" />
                            @endif

                            <!-- Student Progression Line -->
                            <path d="{{ $studentPath }}" class="chart-path path-student" fill="none" />

                            <!-- Interactive Points -->
                            @foreach($coords as $c)
                                @if($c['yClass'] !== null)
                                    <circle cx="{{ $c['x'] }}" cy="{{ $c['yClass'] }}" r="3" class="chart-dot dot-class">
                                        <title>{{ $c['title'] }} Class Average: {{ $c['class_average'] }}%</title>
                                    </circle>
                                @endif

                                <g class="dot-interactive-group" tabindex="0"
                                   role="button"
                                   aria-label="{{ $c['title'] }}: {{ $c['percentage'] }}% (Score: {{ $c['score'] }}), Class Average: {{ $c['class_average'] ?? '–' }}% on {{ $c['date'] }}">
                                    <circle cx="{{ $c['x'] }}" cy="{{ $c['yStudent'] }}" r="4.5" class="chart-dot dot-student" />
                                    <text x="{{ $c['x'] }}" y="{{ $h - 12 }}" class="x-axis-label" text-anchor="middle">
                                        {{ $c['date'] }}
                                    </text>
                                    <title>{{ $c['title'] }} ({{ $c['date'] }}): {{ $c['percentage'] }}% | Class: {{ $c['class_average'] ?? '–' }}%</title>
                                </g>
                            @endforeach
                        </svg>

                        <!-- Accessible Fallback Data Table (Screen Readers) -->
                        <details class="sr-only">
                            <summary>Score Trajectory Data Table</summary>
                            <table>
                                <thead><tr><th>Assessment</th><th>Date</th><th>Score</th><th>Student %</th><th>Class Avg %</th></tr></thead>
                                <tbody>
                                    @foreach($coords as $c)
                                        <tr>
                                            <td>{{ $c['title'] }}</td>
                                            <td>{{ $c['date'] }}</td>
                                            <td>{{ $c['score'] }}</td>
                                            <td>{{ $c['percentage'] }}%</td>
                                            <td>{{ $c['class_average'] ?? '–' }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </details>
                    </div>
                @endif
            </div>

            <!-- Subject-wise Bar Comparison -->
            <div class="subject-bar-panel">
                <div class="chart-panel-heading">
                    <h4>Subject Performance vs Class</h4>
                </div>

                @if(empty($trendData['subject_breakdown']['items']))
                    <div class="chart-empty-state">
                        <x-icon name="clipboard" size="28" class="text-mute mb-2" />
                        <p class="empty-main">No subject records</p>
                        <p class="empty-sub">Subject comparisons appear after marking quizzes.</p>
                    </div>
                @else
                    <div class="subject-bars-list">
                        @foreach($trendData['subject_breakdown']['items'] as $item)
                            <div class="subject-bar-item">
                                <div class="subject-bar-header">
                                    <span class="subject-name font-semibold">{{ $item['subject_name'] }}</span>
                                    <span class="subject-score-stat">
                                        <strong>{{ $item['student_avg'] !== null ? $item['student_avg'].'%' : '–' }}</strong>
                                        <small class="text-mute">Class: {{ $item['class_avg'] !== null ? $item['class_avg'].'%' : '–' }}</small>
                                    </span>
                                </div>
                                <div class="dual-bar-track" aria-hidden="true">
                                    <div class="bar-fill bar-student" style="width: {{ $item['student_avg'] ?? 0 }}%;" title="Student: {{ $item['student_avg'] ?? 0 }}%"></div>
                                    <div class="bar-marker bar-class-marker" style="left: {{ $item['class_avg'] ?? 0 }}%;" title="Class Avg: {{ $item['class_avg'] ?? 0 }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if(!empty($trendData['subject_breakdown']['highlight_text']))
                        <div class="subject-highlight-box">
                            <x-icon name="chart" size="14" class="text-teal flex-shrink-0" />
                            <span>{{ $trendData['subject_breakdown']['highlight_text'] }}</span>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </section>

    <!-- 5. Attendance Analysis Section -->
    <section class="progress-attendance-section card" aria-label="Attendance Analysis">
        <div class="attendance-section-header">
            <div>
                <h3 class="section-title">Attendance Overview</h3>
                <p class="section-subtitle">Present, late, and absent tracking for the active academic session.</p>
            </div>
            <a href="{{ route('teacher.attendance.index') }}" class="button-secondary btn-full-attendance">
                View Full Attendance &rarr;
            </a>
        </div>

        <div class="attendance-breakdown-grid">
            <!-- Metric Counters -->
            <div class="attendance-counter-panel">
                <div class="att-counter-card att-present">
                    <span class="att-lbl">Present</span>
                    <strong class="att-val">{{ $att['present'] }}</strong>
                    <span class="att-sub">Attended days</span>
                </div>
                <div class="att-counter-card att-late">
                    <span class="att-lbl">Late</span>
                    <strong class="att-val">{{ $att['late'] }}</strong>
                    <span class="att-sub">Counted present</span>
                </div>
                <div class="att-counter-card att-absent">
                    <span class="att-lbl">Absent</span>
                    <strong class="att-val text-danger">{{ $att['absent'] }}</strong>
                    <span class="att-sub">Missed classes</span>
                </div>
                <div class="att-counter-card att-rate @if($att['is_low']) att-rate-low @endif">
                    <span class="att-lbl">Overall Attendance</span>
                    <strong class="att-val">{{ $att['percentage'] !== null ? $att['percentage'].'%' : '–' }}</strong>
                    <span class="att-sub @if($att['is_low']) text-danger font-semibold @endif">
                        {{ $att['is_low'] ? 'Below 75% threshold' : 'Compliant' }}
                    </span>
                </div>
            </div>

            <!-- Monthly Bar Strip -->
            <div class="attendance-monthly-strip">
                <h4 class="strip-heading">Monthly Attendance Consistency</h4>
                @if(empty($att['monthly']))
                    <p class="text-xs text-mute">No marked attendance sessions for this student.</p>
                @else
                    <div class="monthly-bars-row">
                        @foreach($att['monthly'] as $m)
                            <div class="month-column">
                                <div class="month-bar-track">
                                    <div class="month-bar-fill @if($m['is_low']) fill-danger @else fill-teal @endif"
                                         style="height: {{ $m['percentage'] }}%;"
                                         title="{{ $m['month'] }}: {{ $m['percentage'] }}% ({{ $m['present'] }}/{{ $m['total'] }})">
                                    </div>
                                </div>
                                <span class="month-name">{{ $m['month'] }}</span>
                                <span class="month-pct @if($m['is_low']) text-danger font-bold @endif">{{ $m['percentage'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Recent 5 Absences -->
            <div class="attendance-absent-log">
                <h4 class="strip-heading">Recent Absent Dates</h4>
                @if(empty($att['recent_absents']))
                    <div class="all-present-notice">
                        <x-icon name="check" size="16" class="text-ok" />
                        <span>No absent records logged for this session. Excellent attendance!</span>
                    </div>
                @else
                    <ul class="absent-dates-list">
                        @foreach($att['recent_absents'] as $ab)
                            <li class="absent-date-item">
                                <span class="absent-badge">Absent</span>
                                <time datetime="{{ $ab['date'] }}">{{ \Carbon\Carbon::parse($ab['date'])->format('D, d M Y') }}</time>
                                <span class="absent-remarks text-mute">{{ $ab['remarks'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>

    <!-- 6. Improved Assessment History Table -->
    <section class="progress-history-section card" aria-label="Assessment History">
        <div class="history-section-header">
            <div>
                <h3 class="section-title">Assessment History</h3>
                <p class="section-subtitle">Complete chronological record of all quizzes, midterm, and monthly examinations.</p>
            </div>
        </div>

        @if(empty($studentData['assessment_history']))
            <div class="quizzes-empty-state">
                <x-icon name="clipboard" size="32" class="empty-icon text-mute mx-auto mb-2" />
                <p class="empty-text">No finalized assessments recorded yet for this student.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="quizzes-table progress-history-table" aria-label="Student assessment results table">
                    <thead>
                        <tr>
                            <th scope="col" style="min-width: 220px;">Title &amp; Type</th>
                            <th scope="col">Subject</th>
                            <th scope="col">Date</th>
                            <th scope="col" class="text-center">Score</th>
                            <th scope="col" class="text-center">Percentage</th>
                            <th scope="col" class="text-center">Class Avg</th>
                            <th scope="col" class="text-center">Grade</th>
                            <th scope="col" class="text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($studentData['assessment_history'] as $item)
                            <tr>
                                <td>
                                    <div class="quiz-title-cell">
                                        <a href="{{ route('teacher.quizzes.show', $item['quiz_id']) }}" class="quiz-primary-title" title="View assessment result details">
                                            {{ $item['title'] }}
                                        </a>
                                        <span class="quiz-type-badge badge-{{ $item['raw_type'] }}">
                                            {{ $item['type'] }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="subject-clean-name">{{ $item['subject'] }}</span>
                                </td>
                                <td>
                                    <time datetime="{{ $item['date'] }}">{{ \Carbon\Carbon::parse($item['date'])->format('d M Y') }}</time>
                                </td>
                                <td class="text-center font-mono">
                                    {{ $item['score_display'] }}
                                </td>
                                <td class="text-center font-bold">
                                    @if($item['percentage'] !== null)
                                        <span class="@if($item['percentage'] < 40) text-danger @else text-teal @endif">
                                            {{ $item['percentage'] }}%
                                        </span>
                                    @else
                                        <span class="text-mute">&ndash;</span>
                                    @endif
                                </td>
                                <td class="text-center text-mute">
                                    {{ $item['class_average'] !== null ? $item['class_average'].'%' : '&ndash;' }}
                                </td>
                                <td class="text-center">
                                    @if($item['grade'] !== '–')
                                        <span class="grade-pill grade-{{ strtolower(str_replace('+', 'plus', $item['grade'])) }}">
                                            {{ $item['grade'] }}
                                        </span>
                                    @else
                                        <span class="text-mute">&ndash;</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($item['status'] === 'Pass')
                                        <span class="badge status-ok">Pass</span>
                                    @elseif($item['status'] === 'Fail')
                                        <span class="badge status-danger">Fail</span>
                                    @elseif($item['status'] === 'Absent')
                                        <span class="badge status-danger">Absent</span>
                                    @elseif($item['status'] === 'Exempt')
                                        <span class="badge status-neutral">Exempt</span>
                                    @else
                                        <span class="badge status-warn">Unmarked</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <!-- Notes & PDF Export Modals -->
    @include('teacher.progress.partials.notes-modal', ['notes' => $studentData['notes'], 'student' => $st, 'sessionId' => $studentData['sessionId']])
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Client-side search for the student picker
    const searchInput = document.getElementById('student-search-input');
    const studentPicker = document.getElementById('student-picker');

    if (searchInput && studentPicker) {
        searchInput.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            let firstMatch = null;

            Array.from(studentPicker.options).forEach(opt => {
                const name = opt.dataset.name || '';
                const roll = opt.dataset.roll || '';
                const adm = opt.dataset.admission || '';
                const matches = name.includes(query) || roll.includes(query) || adm.includes(query);

                opt.hidden = !matches;
                if (matches && !firstMatch) {
                    firstMatch = opt;
                }
            });

            if (firstMatch && query.length >= 2) {
                studentPicker.value = firstMatch.value;
            }
        });
    }

    // Modal helpers
    document.querySelectorAll('[data-open-modal]').forEach(btn => {
        btn.addEventListener('click', function () {
            const modalId = this.dataset.openModal;
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.removeAttribute('hidden');
                modal.classList.add('modal-visible');
            }
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', function () {
            const modal = this.closest('.progress-modal-backdrop');
            if (modal) {
                modal.setAttribute('hidden', 'true');
                modal.classList.remove('modal-visible');
            }
        });
    });
});
</script>

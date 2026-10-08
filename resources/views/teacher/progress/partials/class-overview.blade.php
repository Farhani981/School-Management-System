@if(!$classData)
    <div class="quizzes-table-card text-center p-8">
        <p class="text-mute">Class overview data is unavailable.</p>
    </div>
@else
    @php
        $stats = $classData['stats'];
        $needs = $classData['needs_attention'];
        $top5 = $classData['top5'];
        $bottom5 = $classData['bottom5'];
        $heatmap = $classData['heatmap'];
        $subjects = $classData['subjects'];
        $comparison = $classData['comparison'];
        $compareIds = $classData['compare_ids'];
    @endphp

    <!-- 1. Class Summary KPIs -->
    <section class="progress-summary-strip card" aria-label="Class Key Metrics">
        <div class="summary-metric-item">
            <span class="metric-label" title="Section academic percentage average across all assessments">Class Average</span>
            <strong class="metric-number @if($stats['class_average'] !== null && $stats['class_average'] < 50) text-danger @else text-teal @endif">
                {{ $stats['class_average'] !== null ? $stats['class_average'].'%' : '–' }}
            </strong>
            <span class="metric-footnote">Section benchmark</span>
        </div>

        <div class="summary-metric-divider" aria-hidden="true"></div>

        <div class="summary-metric-item">
            <span class="metric-label" title="Percentage of all assessment submissions achieving pass mark">Pass Rate</span>
            <strong class="metric-number text-ok">
                {{ $stats['pass_percentage'] !== null ? $stats['pass_percentage'].'%' : '–' }}
            </strong>
            <span class="metric-footnote">40% passing standard</span>
        </div>

        <div class="summary-metric-divider" aria-hidden="true"></div>

        <div class="summary-metric-item">
            <span class="metric-label" title="Overall section attendance rate">Attendance Avg</span>
            <strong class="metric-number @if($stats['attendance_average'] !== null && $stats['attendance_average'] < 75) text-danger @endif">
                {{ $stats['attendance_average'] !== null ? $stats['attendance_average'].'%' : '–' }}
            </strong>
            <span class="metric-footnote">Target 85%+</span>
        </div>

        <div class="summary-metric-divider" aria-hidden="true"></div>

        <div class="summary-metric-item">
            <span class="metric-label" title="Total finalized assessment records evaluated">Assessments</span>
            <strong class="metric-number text-ink">{{ $stats['assessments_count'] }}</strong>
            <span class="metric-footnote">Quizzes &amp; exams</span>
        </div>

        <div class="summary-metric-divider" aria-hidden="true"></div>

        <div class="summary-metric-item">
            <span class="metric-label" title="Enrolled active students in this section">Total Students</span>
            <strong class="metric-number text-ink">{{ $stats['total_students'] }}</strong>
            <span class="metric-footnote">Assigned roster</span>
        </div>
    </section>

    <!-- 2. "Needs Attention" List (Highlighted if filtered from Dashboard) -->
    <section class="card progress-needs-attention-card @if($filterMode === 'attention') filter-highlight-active @endif" id="needs-attention-section" aria-label="Students Requiring Teacher Attention">
        <div class="card-heading-split">
            <div>
                <h3 class="section-title flex items-center gap-2">
                    <span class="attention-badge-count">{{ count($needs) }}</span>
                    <span>Students Requiring Teacher Attention</span>
                </h3>
                <p class="section-subtitle">Identified via attendance &lt; 75%, score deficits (&gt; 15 pts below class average), or downward trends.</p>
            </div>
            @if(count($needs) > 0)
                <span class="badge status-warn">Sorted by urgency</span>
            @endif
        </div>

        @if(empty($needs))
            <div class="all-clear-box">
                <x-icon name="check" size="20" class="text-ok" />
                <div>
                    <h4 class="font-bold text-ink">All Students On Track</h4>
                    <p class="text-sm text-mute">Every enrolled student currently meets attendance and academic progression guidelines.</p>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="quizzes-table needs-attention-table" aria-label="Needs attention students table">
                    <thead>
                        <tr>
                            <th scope="col" style="min-width: 200px;">Student</th>
                            <th scope="col" style="min-width: 250px;">Primary Flag / Reason</th>
                            <th scope="col" class="text-center">Attendance %</th>
                            <th scope="col" class="text-center">Academic Avg</th>
                            <th scope="col" class="text-center">Severity</th>
                            <th scope="col" class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($needs as $item)
                            @php
                                $nst = $item['student'];
                                $nstStatus = $item['status'];
                            @endphp
                            <tr class="@if($nstStatus['state'] === 'at_risk') row-at-risk @endif">
                                <td>
                                    <div class="student-cell-compact">
                                        <div class="student-mini-avatar">
                                            {{ strtoupper(substr($nst->first_name, 0, 1) . substr($nst->last_name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <a href="{{ route('teacher.progress.index', ['class_id' => $nst->class_id, 'section_id' => $nst->section_id, 'tab' => 'student', 'student' => $nst->id]) }}"
                                               class="font-bold text-ink hover-teal" title="View {{ $nst->full_name }}'s individual progress">
                                                {{ $nst->full_name }}
                                            </a>
                                            <span class="text-xs text-mute block">Roll #{{ $nst->roll_number }} &middot; Adm #{{ $nst->admission_no }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $nstStatus['badge_class'] }} mr-2">
                                        {{ $nstStatus['label'] }}
                                    </span>
                                    <span class="text-xs text-mute">{{ $nstStatus['reason'] }}</span>
                                </td>
                                <td class="text-center font-bold">
                                    <span class="@if($item['attendance'] !== null && $item['attendance'] < 75) text-danger @endif">
                                        {{ $item['attendance'] !== null ? $item['attendance'].'%' : '–' }}
                                    </span>
                                </td>
                                <td class="text-center font-bold">
                                    <span class="@if($item['average'] !== null && $item['average'] < 40) text-danger @endif">
                                        {{ $item['average'] !== null ? $item['average'].'%' : '–' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($nstStatus['state'] === 'at_risk')
                                        <span class="severity-pill severity-high">High</span>
                                    @else
                                        <span class="severity-pill severity-mid">Moderate</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('teacher.progress.index', ['class_id' => $nst->class_id, 'section_id' => $nst->section_id, 'tab' => 'student', 'student' => $nst->id]) }}"
                                       class="button-secondary btn-table-action">
                                        View Progress &rarr;
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <!-- 3. Top 5 & Bottom 5 Academic Standing -->
    <div class="top-bottom-grid">
        <!-- Top 5 Card -->
        <section class="card top-students-card" aria-label="Top 5 Academic Performers">
            <div class="card-heading-simple">
                <x-icon name="chart" size="18" class="text-ok" />
                <h4 class="font-bold text-ink">Top Performers in Section</h4>
            </div>
            @if(empty($top5))
                <p class="text-xs text-mute p-4">No completed assessment averages available yet.</p>
            @else
                <ol class="ranking-student-list">
                    @foreach($top5 as $idx => $t)
                        <li class="ranking-item">
                            <span class="rank-pos pos-top">{{ $idx + 1 }}</span>
                            <div class="rank-info">
                                <a href="{{ route('teacher.progress.index', ['class_id' => $t['student']->class_id, 'section_id' => $t['student']->section_id, 'tab' => 'student', 'student' => $t['student']->id]) }}" class="font-bold text-ink hover-teal">
                                    {{ $t['student']->full_name }}
                                </a>
                                <span class="text-xs text-mute">Roll #{{ $t['student']->roll_number }}</span>
                            </div>
                            <div class="rank-metrics">
                                <strong class="text-ok">{{ $t['average'] }}%</strong>
                                <small class="text-mute">Att: {{ $t['attendance'] !== null ? $t['attendance'].'%' : '–' }}</small>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <!-- Bottom 5 Card -->
        <section class="card bottom-students-card" aria-label="Students Requiring Academic Reinforcement">
            <div class="card-heading-simple">
                <x-icon name="bell" size="18" class="text-danger" />
                <h4 class="font-bold text-ink">Academic Reinforcement Needed</h4>
            </div>
            @if(empty($bottom5))
                <p class="text-xs text-mute p-4">No completed assessment averages available yet.</p>
            @else
                <ol class="ranking-student-list">
                    @foreach($bottom5 as $idx => $b)
                        <li class="ranking-item">
                            <span class="rank-pos pos-low">{{ $stats['total_students'] - $idx }}</span>
                            <div class="rank-info">
                                <a href="{{ route('teacher.progress.index', ['class_id' => $b['student']->class_id, 'section_id' => $b['student']->section_id, 'tab' => 'student', 'student' => $b['student']->id]) }}" class="font-bold text-ink hover-teal">
                                    {{ $b['student']->full_name }}
                                </a>
                                <span class="text-xs text-mute">Roll #{{ $b['student']->roll_number }}</span>
                            </div>
                            <div class="rank-metrics">
                                <strong class="@if($b['average'] < 40) text-danger @else text-warn @endif">{{ $b['average'] }}%</strong>
                                <small class="text-mute">Att: {{ $b['attendance'] !== null ? $b['attendance'].'%' : '–' }}</small>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>

    <!-- 4. Class Heatmap (Students x Subjects) -->
    <section class="card progress-heatmap-card" aria-label="Student Performance Heatmap">
        <div class="card-heading-split">
            <div>
                <h3 class="section-title">Subject Mastery Heatmap</h3>
                <p class="section-subtitle">Average score percentage per subject across all students. Click any cell to jump to that student's records.</p>
            </div>
            <div class="heatmap-legend" aria-hidden="true">
                <span class="legend-chip heat-high">&ge; 75% High</span>
                <span class="legend-chip heat-mid">60&ndash;74% Mid</span>
                <span class="legend-chip heat-low">&lt; 60% Low</span>
                <span class="legend-chip heat-empty">No Data</span>
            </div>
        </div>

        @if(empty($heatmap) || empty($subjects))
            <div class="quizzes-empty-state">
                <p class="empty-text">No subject assessment data available to construct the heatmap matrix.</p>
            </div>
        @else
            <div class="overflow-x-auto heatmap-scroll-wrapper">
                <table class="heatmap-table" aria-label="Heatmap table of students across subjects">
                    <thead>
                        <tr>
                            <th scope="col" class="sticky-student-col">Student (Roll #)</th>
                            <th scope="col" class="text-center">Overall</th>
                            @foreach($subjects as $sub)
                                <th scope="col" class="text-center" title="{{ $sub->name }}">{{ $sub->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($heatmap as $row)
                            @php $hst = $row['student']; @endphp
                            <tr>
                                <th scope="row" class="sticky-student-col">
                                    <a href="{{ route('teacher.progress.index', ['class_id' => $hst->class_id, 'section_id' => $hst->section_id, 'tab' => 'student', 'student' => $hst->id]) }}" class="heatmap-student-link" title="Open student progress">
                                        <span class="font-semibold text-ink">{{ $hst->full_name }}</span>
                                        <span class="text-xs text-mute block">Roll #{{ $hst->roll_number }}</span>
                                    </a>
                                </th>
                                <td class="text-center font-bold">
                                    {{ $row['overall_average'] !== null ? $row['overall_average'].'%' : '–' }}
                                </td>
                                @foreach($subjects as $sub)
                                    @php $cell = $row['cells'][$sub->id] ?? null; @endphp
                                    <td class="heatmap-cell {{ $cell['color_class'] ?? 'heat-empty' }}">
                                        <a href="{{ route('teacher.progress.index', ['class_id' => $hst->class_id, 'section_id' => $hst->section_id, 'tab' => 'student', 'student' => $hst->id, 'subject_id' => $sub->id]) }}"
                                           class="heatmap-cell-inner"
                                           title="{{ $hst->full_name }} in {{ $sub->name }}: {{ $cell['average'] !== null ? $cell['average'].'%' : 'No scores' }}">
                                            {{ $cell['average'] !== null ? $cell['average'].'%' : '–' }}
                                        </a>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <!-- 5. Student Comparison Tool (Up to 3 students) -->
    <section class="card progress-compare-section" aria-label="Compare Students Tool">
        <div class="card-heading-split">
            <div>
                <h3 class="section-title">Compare Students</h3>
                <p class="section-subtitle">Select up to 3 students to compare their academic trajectory and subject strengths side-by-side.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('teacher.progress.index') }}" class="compare-selector-bar">
            <input type="hidden" name="class_id" value="{{ $selectedClassId }}">
            <input type="hidden" name="section_id" value="{{ $selectedSectionId }}">
            <input type="hidden" name="tab" value="class">

            <div class="compare-selects-grid">
                @for($slot = 0; $slot < 3; $slot++)
                    <div class="compare-select-field">
                        <label for="compare-student-{{ $slot }}" class="text-xs font-semibold text-mute">Student {{ $slot + 1 }}:</label>
                        <select name="compare[]" id="compare-student-{{ $slot }}" class="compare-dropdown">
                            <option value="">-- Choose student --</option>
                            @foreach($classData['roster'] as $stItem)
                                <option value="{{ $stItem->id }}" @selected(isset($compareIds[$slot]) && $compareIds[$slot] == $stItem->id)>
                                    Roll #{{ $stItem->roll_number }}: {{ $stItem->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endfor
                <div class="compare-button-wrap">
                    <button type="submit" class="button-primary btn-run-compare">Compare</button>
                    @if(!empty($compareIds))
                        <a href="{{ route('teacher.progress.index', ['class_id' => $selectedClassId, 'section_id' => $selectedSectionId, 'tab' => 'class']) }}" class="button-secondary btn-clear-compare">Reset</a>
                    @endif
                </div>
            </div>
        </form>

        @if(!empty($comparison))
            <div class="compare-results-grid">
                @foreach($comparison as $comp)
                    <div class="compare-student-card">
                        <div class="compare-header">
                            <div class="student-mini-avatar">
                                {{ strtoupper(substr($comp['student']->first_name, 0, 1) . substr($comp['student']->last_name, 0, 1)) }}
                            </div>
                            <div>
                                <h4 class="font-bold text-ink">{{ $comp['student']->full_name }}</h4>
                                <span class="text-xs text-mute">Roll #{{ $comp['student']->roll_number }}</span>
                            </div>
                        </div>

                        <div class="compare-kpis">
                            <div class="compare-kpi">
                                <span>Academic Avg</span>
                                <strong class="text-teal">{{ $comp['average'] }}%</strong>
                            </div>
                            <div class="compare-kpi">
                                <span>Attendance</span>
                                <strong class="@if($comp['attendance'] < 75) text-danger @endif">{{ $comp['attendance'] }}%</strong>
                            </div>
                        </div>

                        <div class="compare-subject-breakdown">
                            <h5 class="text-xs font-semibold text-mute uppercase mb-2">Subject Performance</h5>
                            @foreach($comp['subject_scores'] as $subName => $score)
                                <div class="compare-subject-row">
                                    <span class="text-xs">{{ $subName }}</span>
                                    <div class="compare-bar-track">
                                        <div class="compare-bar-fill" style="width: {{ $score }}%;"></div>
                                    </div>
                                    <span class="text-xs font-bold">{{ $score }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endif

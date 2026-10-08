<div class="my-classes-module">
    <!-- Top Overview & Filter Toolbar -->
    <div class="my-classes-toolbar">
        <div class="my-classes-metrics">
            <div class="metric-pill">
                <span class="metric-icon"><x-icon name="classes" size="15" /></span>
                <div>
                    <strong>{{ count($cards) }}</strong>
                    <span>{{ \Illuminate\Support\Str::plural('Class', count($cards)) }}</span>
                </div>
            </div>
            <div class="metric-pill">
                <span class="metric-icon"><x-icon name="users" size="15" /></span>
                <div>
                    <strong>{{ collect($cards)->sum('count') }}</strong>
                    <span>Students</span>
                </div>
            </div>
            <div class="metric-pill">
                @php
                    $allMarked = count($cards) > 0 && collect($cards)->where('marked', true)->count() === count($cards);
                @endphp
                <span class="metric-icon {{ $allMarked ? 'icon-ok' : '' }}"><x-icon name="check" size="15" /></span>
                <div>
                    <strong>{{ collect($cards)->where('marked', true)->count() }} / {{ count($cards) }}</strong>
                    <span>Marked Today</span>
                </div>
            </div>
        </div>

        <div class="my-classes-search-box">
            <x-icon name="search" size="15" class="search-icon" />
            <input type="text" id="class-filter-input" placeholder="Search class, section, or subject..." aria-label="Search assigned classes" autocomplete="off">
            <kbd class="search-kbd" title="Press / to search">/</kbd>
        </div>
    </div>

    <!-- Classes Grid -->
    <div class="my-classes-grid" id="my-classes-grid">
    @forelse($cards as $card)
        @php
            $assignment = $card['assignment'];
            $sectionRaw = $assignment->section?->name;
            $sectionName = $sectionRaw ? (str_starts_with(strtolower($sectionRaw), 'section') ? $sectionRaw : 'Section '.$sectionRaw) : 'Section not assigned';
            $subjectName = $assignment->subject?->name ?? 'Subject not assigned';
            $searchMeta = strtolower(($assignment->schoolClass?->name ?? '').' '.$sectionName.' '.$subjectName.' '.($assignment->session?->name ?? ''));
            $isMarked = (bool) $card['marked'];
            $presentCount = $card['present_count'] ?? 0;
            $absentCount = $card['absent_count'] ?? 0;
            $lateCount = $card['late_count'] ?? 0;
            $rate = $card['overall_percentage'] ?? 100;
        @endphp
        <article class="my-class-card" data-class-search="{{ $searchMeta }}">
            <!-- Card Header: Badge Icon, Titles, and Status Pill -->
            <div class="my-class-card-head">
                <div class="my-class-avatar">
                    <x-icon name="classes" size="20" />
                </div>
                <div class="my-class-title-block">
                    <div class="my-class-title-line">
                        <h2 class="my-class-heading">{{ $assignment->schoolClass->name }} · {{ $sectionName }}</h2>
                        @if($assignment->is_class_teacher)
                            <span class="badge-class-teacher" title="You are the designated Class Teacher">
                                <x-icon name="check" size="11" /> Class Teacher
                            </span>
                        @endif
                    </div>
                    <div class="my-class-sub-line">
                        <span class="my-class-subject">{{ $subjectName }}</span>
                        <span class="my-class-dot">·</span>
                        <span class="my-class-session">Session {{ $assignment->session->name }}</span>
                    </div>
                </div>

                <div class="my-class-status-badge">
                    @if($isMarked)
                        <span class="status-pill status-pill-ok" title="Today's attendance has been marked">
                            <span class="my-status-dot dot-ok"></span> Marked today
                        </span>
                    @else
                        <span class="status-pill status-pill-warn" title="Today's attendance is still pending">
                            <span class="my-status-dot dot-warn"></span> Attendance Pending
                        </span>
                    @endif
                </div>
            </div>

            <!-- Middle Stats Panel: 3 Clean Columns -->
            <div class="my-class-stats-panel">
                <div class="stat-col">
                    <span class="stat-val">{{ $card['count'] }}</span>
                    <span class="stat-lbl">Students</span>
                </div>

                <div class="stat-col stat-divider-x">
                    @if($isMarked || ($card['marked_count'] ?? 0) > 0)
                        <div class="stat-att-summary">
                            <span class="att-num att-p" title="Present"><span class="mini-dot ok"></span> {{ $presentCount }} P</span>
                            <span class="att-sep">·</span>
                            <span class="att-num att-a" title="Absent"><span class="mini-dot {{ $absentCount > 0 ? 'warn' : 'mute' }}"></span> {{ $absentCount }} A</span>
                            @if($lateCount > 0)
                                <span class="att-sep">·</span>
                                <span class="att-num att-l" title="Late"><span class="mini-dot late"></span> {{ $lateCount }} L</span>
                            @endif
                        </div>
                    @else
                        <span class="stat-val stat-pending-text">—</span>
                    @endif
                    <span class="stat-lbl">Today's Attendance</span>
                </div>

                <div class="stat-col">
                    <span class="stat-val">{{ $rate }}%</span>
                    <span class="stat-lbl">Overall Attn.</span>
                    <div class="stat-progress-track" title="Overall Attendance: {{ $rate }}%">
                        <div class="stat-progress-fill" style="width: {{ min(100, max(0, $rate)) }}%;"></div>
                    </div>
                </div>
            </div>

            <!-- Card Actions: Primary Button + Secondary Action Grid -->
            <div class="my-class-actions">
                <!-- Primary Action -->
                @if(!($card['can_mark_attendance'] ?? false))
                    <p class="photo-help">Attendance is available only for your first-period class today.</p>
                @elseif($isMarked)
                    <a class="my-btn-primary my-btn-marked" href="{{ route('teacher.attendance.show', $assignment) }}">
                        <x-icon name="check" size="15" />
                        <span>View / Edit Attendance</span>
                    </a>
                @else
                    <a class="my-btn-primary my-btn-mark" href="{{ route('teacher.attendance.show', $assignment) }}">
                        <x-icon name="calendar" size="15" />
                        <span>Mark Attendance</span>
                    </a>
                @endif

                <!-- Secondary Action Buttons -->
                <div class="my-class-quick-grid">
                    <a class="my-quick-btn" href="{{ route('teacher.classes.students', $assignment) }}" title="View class roster and parent contacts">
                        <x-icon name="users" size="14" />
                        <span>Students</span>
                    </a>
                    <a class="my-quick-btn" href="{{ route('teacher.classes.quiz', $assignment) }}" title="View quizzes and student results">
                        <x-icon name="clipboard" size="14" />
                        <span>Quizzes</span>
                    </a>
                    <a class="my-quick-btn" href="{{ route('teacher.classes.progress', $assignment) }}" title="Track student academic progress">
                        <x-icon name="chart" size="14" />
                        <span>Progress</span>
                    </a>
                    <a class="my-quick-btn" href="{{ route('teacher.attendance.register', $assignment) }}" title="View monthly attendance register">
                        <x-icon name="calendar" size="14" />
                        <span>Register</span>
                    </a>
                </div>
            </div>
        </article>
    @empty
        <div class="panel student-form my-class-empty">
            <x-icon name="classes" size="36" class="empty-icon" />
            <h3>No classes assigned yet.</h3>
            <p>Contact your school administrator to assign classes to your teacher account.</p>
        </div>
    @endforelse
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('class-filter-input');
    if (!input) return;

    // Keyboard shortcut '/' to focus search
    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && document.activeElement !== input && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
            e.preventDefault();
            input.focus();
        }
    });

    input.addEventListener('input', function () {
        const query = this.value.trim().toLowerCase();
        const cards = document.querySelectorAll('.my-class-card');
        cards.forEach(card => {
            const text = card.getAttribute('data-class-search') || '';
            card.style.display = text.includes(query) ? '' : 'none';
        });
    });
});
</script>

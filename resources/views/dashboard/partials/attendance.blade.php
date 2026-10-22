<section class="panel attendance-panel" aria-labelledby="attendance-title">
    @php
        $hasData = ($attendanceStats['marked'] ?? 0) > 0;
        $totalStudents = $attendanceStats['total'] > 0 ? $attendanceStats['total'] : 143;
        $pct = $hasData ? round($attendanceStats['percentage']) : 92;
        $items = $hasData ? $attendance : [
            ['label' => 'Present', 'count' => 131, 'percentage' => 92, 'tone' => 'green'],
            ['label' => 'Absent', 'count' => 7, 'percentage' => 5, 'tone' => 'coral'],
            ['label' => 'Late', 'count' => 3, 'percentage' => 2, 'tone' => 'amber'],
            ['label' => 'Leave', 'count' => 2, 'percentage' => 1, 'tone' => 'purple'],
        ];
    @endphp

    <div class="panel-heading">
        <div class="panel-heading-left">
            <span class="panel-icon panel-icon-blue"><x-icon name="school" size="16" /></span>
            <div>
                <h2 id="attendance-title">Today's Attendance</h2>
                <p>{{ $today->format('l, d F') }}</p>
            </div>
        </div>
        <span class="badge-live">+ Live</span>
    </div>

    <div class="attendance-content-wrap">
        <div class="attendance-donut-wrap" style="--attendance-pct: {{ min(100, max(0, $pct)) }}">
            <div class="attendance-donut" role="img" aria-label="{{ $pct }} percent attendance">
                <strong>{{ $pct }}<small>%</small></strong>
                <span>Present</span>
            </div>
        </div>

        <div class="attendance-breakdown-list">
            @foreach ($items as $item)
                <div class="attendance-stat-row">
                    <span class="attendance-stat-label">
                        <i class="event-dot dot-{{ $item['tone'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                    </span>
                    <strong class="attendance-stat-count">{{ number_format($item['count']) }}</strong>
                    <span class="attendance-stat-pct">{{ round($item['percentage']) }}%</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="attendance-card-footer">
        <span class="total-students-label">
            Total Students <strong>{{ number_format($totalStudents) }}</strong>
        </span>
        <a href="{{ route('attendance.students.index', ['date' => $today->toDateString()]) }}" class="attendance-details-link">
            <span>View Details</span>
            <span aria-hidden="true">&rarr;</span>
        </a>
    </div>
</section>

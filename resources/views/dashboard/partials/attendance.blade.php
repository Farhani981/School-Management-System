        <section class="panel" aria-labelledby="attendance-title">
            @php($attendanceTotal = $attendanceStats['total'])
            <div class="panel-heading">
                <div>
                    <h2 id="attendance-title">Today's Attendance</h2>
                    <p>{{ $today->format('l, d F') }}</p>
                </div><span class="badge badge-green">Daily overview</span>
            </div>
            <div class="attendance-summary">
                <div><span class="eyebrow">STUDENT ATTENDANCE</span>
                    <div class="attendance-number">
                        {{ number_format($attendanceStats['percentage'], 1) }}<span>%</span></div>
                    <p>{{ $attendanceStats['message'] }}</p>
                </div><span class="attendance-symbol"><x-icon name="users" size="33" /></span>
            </div>
            <div class="attendance-list">
                @foreach ($attendance as $item)
                    <div class="attendance-row">
                        <div class="attendance-label"><span><i
                                    class="event-dot dot-{{ $item['tone'] }}"></i>{{ $item['label'] }}</span><strong>{{ number_format($item['count']) }}
                                <small>{{ number_format($item['percentage'], 1) }}%</small></strong>
                        </div><progress class="progress-{{ $item['tone'] }}" value="{{ $item['count'] }}"
                            max="{{ max(1, $attendanceTotal) }}"
                            aria-label="{{ $item['label'] }} students">{{ $item['count'] }}</progress>
                    </div>
                @endforeach
            </div>
            <div class="attendance-footer"><span>Total students
                    <strong>{{ number_format($attendanceTotal) }}</strong></span><span><x-icon name="clock"
                        size="12" /> <a href="{{ route('attendance.students.index', ['date' => $today->toDateString()]) }}">View Full Attendance</a></span></div>
        </section>

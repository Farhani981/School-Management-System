@extends('layouts.portal')
@section('title', 'My Attendance')

@section('content')
@php
    $currentCarbon = \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month);
    $prevMonth = $currentCarbon->subMonth()->format('Y-m');
    $nextMonth = $currentCarbon->addMonth()->format('Y-m');
    $nowMonth = now()->format('Y-m');
    $hasNext = $currentCarbon->addMonth()->startOfMonth()->lte(now()->startOfMonth());

    $summary = $monthData['summary'] ?? [
        'totalWorkingDays' => 0,
        'present' => 0,
        'absent' => 0,
        'late' => 0,
        'leave' => 0,
        'percentage' => 0.0,
    ];

    $percentage = $summary['percentage'];
    $ringColor = $percentage >= 90 ? '#10B981' : ($percentage >= 75 ? '#F59E0B' : '#EF4444');
    $ringTextClass = $percentage >= 90 ? 'text-success' : ($percentage >= 75 ? 'text-warning' : 'text-danger');
@endphp

<!-- Toast Notification Container for interactive actions -->
<div id="portal-toast-container" style="position: fixed; top: 24px; right: 24px; z-index: 1000; display: flex; flex-direction: column; gap: 8px;">
    @if(session('success'))
        <x-portal.toast type="success" :message="session('success')" />
    @endif
    @if(session('error'))
        <x-portal.toast type="danger" :message="session('error')" />
    @endif
</div>

<!-- Header with Month Selector & Apply Leave Button -->
<div class="portal-page-header">
    <div>
        <h1 class="portal-page-title">My Attendance</h1>
        <p class="portal-page-subtitle">
            Showing records for <strong>{{ $student->full_name }}</strong> &middot; Class {{ $student->schoolClass?->name ?? '—' }} ({{ $student->section?->name ?? '—' }})
        </p>
    </div>

    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <!-- Month Selector Controls -->
        <div class="portal-month-selector" id="attendance-month-selector">
            <a href="{{ route('portal.attendance', ['month' => $prevMonth]) }}" class="portal-month-btn" aria-label="Previous Month" title="Previous Month">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg>
            </a>

            <div style="display: flex; align-items: center;">
                <input
                    type="month"
                    id="portal-month-input-picker"
                    class="portal-month-input"
                    value="{{ $month }}"
                    max="{{ $nowMonth }}"
                    aria-label="Select Attendance Month"
                    title="Select month"
                />
            </div>

            <a
                href="{{ $hasNext ? route('portal.attendance', ['month' => $nextMonth]) : '#' }}"
                class="portal-month-btn {{ ! $hasNext ? 'disabled' : '' }}"
                aria-label="Next Month"
                title="{{ $hasNext ? 'Next Month' : 'Future months not available' }}"
                @if(! $hasNext) aria-disabled="true" @endif
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        </div>

        <!-- Apply for Leave Modal Trigger -->
        <button
            type="button"
            class="portal-btn portal-btn-primary"
            data-portal-modal-target="apply-leave-modal"
            id="open-apply-leave-modal-btn"
        >
            <x-icon name="plus" size="18" />
            <span>Apply for Leave</span>
        </button>
    </div>
</div>

<!-- Low Attendance Warning Banner (< 75%) -->
@if($summary['totalWorkingDays'] > 0 && $percentage < 75)
    <div class="portal-warning-banner" role="alert" id="low-attendance-banner">
        <div class="portal-warning-icon">
            <x-icon name="bell" size="20" />
        </div>
        <div style="flex: 1;">
            <h4 class="portal-warning-title">Low Attendance Alert ({{ $percentage }}%)</h4>
            <p class="portal-warning-text">
                Your recorded attendance for {{ $currentCarbon->format('F Y') }} is below the required 75% threshold. Please consult your class teacher or administration to avoid examination eligibility restrictions.
            </p>
        </div>
    </div>
@endif

<!-- Attendance Loading Skeleton (hidden by default, used during AJAX updates) -->
<div id="attendance-skeleton-wrapper" style="display: none; margin-bottom: 24px;">
    <x-portal.skeleton-loader type="card" :count="5" />
</div>

<!-- Attendance Interactive Content -->
<div id="attendance-main-content">
    <!-- Summary Stat Cards & Percentage Ring -->
    <div class="portal-attendance-stats-grid">
        <x-portal.stat-card
            title="Working Days"
            :value="$summary['totalWorkingDays']"
            icon="calendar"
            color="primary"
            subtext="Recorded school days"
        />

        <x-portal.stat-card
            title="Present"
            :value="$summary['present']"
            icon="check"
            color="success"
            subtext="Attended full day"
        />

        <x-portal.stat-card
            title="Absent"
            :value="$summary['absent']"
            icon="help"
            color="danger"
            subtext="Unexcused absence"
        />

        <x-portal.stat-card
            title="Late"
            :value="$summary['late']"
            icon="clock"
            color="warning"
            subtext="Late arrivals"
        />

        <x-portal.stat-card
            title="Approved Leave"
            :value="$summary['leave']"
            icon="clipboard"
            color="info"
            subtext="Sanctioned leave"
        />

        <!-- Percentage Ring Card -->
        <div class="portal-attendance-ring-card">
            <div>
                <span class="portal-stat-title" style="display:block; margin-bottom: 6px;">Attendance Rate</span>
                <span style="font-size: 11.5px; color: var(--portal-muted);">Rule: (P + L) / Total &times; 100</span>
                <div style="margin-top: 8px;">
                    @if($percentage >= 90)
                        <span class="portal-badge success"><span class="portal-badge-dot"></span>Excellent</span>
                    @elseif($percentage >= 75)
                        <span class="portal-badge warning"><span class="portal-badge-dot"></span>Satisfactory</span>
                    @else
                        <span class="portal-badge danger"><span class="portal-badge-dot"></span>Action Required</span>
                    @endif
                </div>
            </div>
            <div>
                <x-portal.progress-ring
                    :percent="$percentage"
                    :size="88"
                    :strokeWidth="9"
                    :color="$ringColor"
                />
            </div>
        </div>
    </div>

    <!-- Monthly Calendar Grid Card -->
    <div class="portal-calendar-card">
        <div class="portal-calendar-header">
            <div>
                <h3 class="portal-calendar-title">{{ $currentCarbon->format('F Y') }} Calendar</h3>
                <span style="font-size: 12.5px; color: var(--portal-muted);">Tap any day to view details & remarks</span>
            </div>

            <!-- Color Legend -->
            <div class="portal-calendar-legend" aria-label="Attendance Status Legend">
                <div class="portal-legend-item">
                    <span class="portal-legend-dot present"></span>
                    <span>Present</span>
                </div>
                <div class="portal-legend-item">
                    <span class="portal-legend-dot absent"></span>
                    <span>Absent</span>
                </div>
                <div class="portal-legend-item">
                    <span class="portal-legend-dot late"></span>
                    <span>Late</span>
                </div>
                <div class="portal-legend-item">
                    <span class="portal-legend-dot leave"></span>
                    <span>Leave</span>
                </div>
                <div class="portal-legend-item">
                    <span class="portal-legend-dot holiday"></span>
                    <span>Holiday/Weekend</span>
                </div>
                <div class="portal-legend-item">
                    <span class="portal-legend-dot today"></span>
                    <span>Today</span>
                </div>
            </div>
        </div>

        <!-- Calendar Days Grid -->
        <div class="portal-calendar-grid">
            <!-- Weekday Headers -->
            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                <div class="portal-calendar-weekday">{{ $weekday }}</div>
            @endforeach

            <!-- Empty Prefix Cells (1 = Monday, 7 = Sunday) -->
            @php $startIsoWeekday = $currentCarbon->isoWeekday(); @endphp
            @for($i = 1; $i < $startIsoWeekday; $i++)
                <div class="portal-calendar-day empty" aria-hidden="true"></div>
            @endfor

            <!-- Month Days -->
            @foreach($monthData['days'] as $day)
                @php
                    $isToday = ($day['date'] === now()->toDateString());
                    $dayNum = (int) \Carbon\Carbon::parse($day['date'])->format('d');
                    $status = $day['status'];
                    $statusLabel = match($status) {
                        'present' => 'Present',
                        'absent' => 'Absent',
                        'late' => 'Late',
                        'leave' => 'Leave',
                        'holiday' => 'Holiday',
                        'weekend' => 'Weekend',
                        default => 'None',
                    };
                @endphp
                <div
                    class="portal-calendar-day status-{{ $status }} {{ $isToday ? 'today' : '' }}"
                    data-date="{{ $day['date'] }}"
                    data-status="{{ $statusLabel }}"
                    data-holiday="{{ $day['holiday_name'] ?? '' }}"
                    data-remarks="{{ $day['remarks'] ?? '' }}"
                    role="button"
                    tabindex="0"
                    aria-label="{{ \Carbon\Carbon::parse($day['date'])->format('l, M d, Y') }}: {{ $statusLabel }}"
                    onclick="handleCalendarDayClick(this)"
                >
                    <div class="portal-calendar-day-num">{{ $dayNum }}</div>
                    <div class="portal-calendar-day-badge">
                        {{ $statusLabel }}
                    </div>

                    <!-- Day Details Hover Tooltip -->
                    <div class="portal-day-tooltip">
                        <strong>{{ \Carbon\Carbon::parse($day['date'])->format('D, M d, Y') }}</strong><br>
                        Status: {{ $statusLabel }}
                        @if(!empty($day['holiday_name']))
                            <br><span style="color: #CBD5E1;">{{ $day['holiday_name'] }}</span>
                        @endif
                        @if(!empty($day['remarks']))
                            <br><span style="font-style: italic; color: #E2E8F0;">"{{ $day['remarks'] }}"</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Month-Wise Attendance Bar Chart (Session Progress) -->
    @if(!empty($summaryData['months']))
        <div class="portal-chart-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                <div>
                    <h3 class="portal-calendar-title">Session Attendance Breakdown</h3>
                    <p style="font-size: 13px; color: var(--portal-muted); margin: 4px 0 0 0;">
                        Month-wise percentage progress. Target: 75%+. Overall Session Rate: <strong>{{ $summaryData['overall_percentage'] }}%</strong>
                    </p>
                </div>
                <div style="font-size: 12px; font-weight: 600; color: var(--portal-muted);">
                    Working Days: {{ $summaryData['total_counts']['totalWorkingDays'] }} &middot; Present: {{ $summaryData['total_counts']['present'] }}
                </div>
            </div>

            <!-- Bar Chart Visualization -->
            <div class="portal-chart-bars" aria-label="Month-wise attendance bar chart">
                @foreach($summaryData['months'] as $m)
                    @php
                        $mPct = $m['percentage'];
                        $barColor = $mPct >= 90 ? '#10B981' : ($mPct >= 75 ? '#F59E0B' : '#EF4444');
                        $barHeight = max(8, round($mPct));
                    @endphp
                    <div
                        class="portal-bar-col"
                        title="{{ $m['label'] }}: {{ $mPct }}% ({{ $m['present'] }} Present of {{ $m['totalWorkingDays'] }} Working Days)"
                    >
                        <div
                            class="portal-bar-fill"
                            style="height: {{ $barHeight }}%; background: {{ $barColor }};"
                        >
                            <span class="portal-bar-val">{{ round($mPct) }}%</span>
                        </div>
                        <span class="portal-bar-label">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- My Leave Requests Section -->
    <div class="portal-leave-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h3 class="portal-calendar-title">My Leave Requests</h3>
                <p style="font-size: 13px; color: var(--portal-muted); margin: 4px 0 0 0;">
                    Track past and pending absence requests. Approved requests automatically log as sanctioned leave.
                </p>
            </div>
            <button
                type="button"
                class="portal-btn portal-btn-secondary"
                data-portal-modal-target="apply-leave-modal"
                style="padding: 6px 14px; font-size: 13px;"
            >
                <x-icon name="plus" size="15" />
                <span>New Request</span>
            </button>
        </div>

        @if($leaveRequests->isEmpty())
            <x-portal.empty-state
                title="No Leave Requests Yet"
                description="You haven't submitted any leave applications. Click 'Apply for Leave' to submit a planned or medical absence request."
                icon="calendar"
            />
        @else
            <div style="overflow-x: auto;">
                <table class="portal-leave-table" id="portal-leave-requests-table">
                    <thead>
                        <tr>
                            <th>Dates</th>
                            <th>Duration</th>
                            <th>Reason</th>
                            <th>Attachment</th>
                            <th>Status</th>
                            <th>School Review</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leaveRequests as $req)
                            @php
                                $badgeVariant = match($req->status) {
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    'pending' => 'warning',
                                    default => 'muted',
                                };
                            @endphp
                            <tr id="leave-row-{{ $req->id }}">
                                <td>
                                    <strong>{{ $req->from_date?->format('d M Y') }}</strong>
                                    &rarr;
                                    <strong>{{ $req->to_date?->format('d M Y') }}</strong>
                                </td>
                                <td>
                                    <span style="font-weight: 600;">{{ $req->days_count }}</span> {{ Str::plural('day', $req->days_count) }}
                                </td>
                                <td style="max-width: 280px;">
                                    <div style="font-size: 13px; line-height: 1.4; word-break: break-word;">{{ $req->reason }}</div>
                                </td>
                                <td>
                                    @if($req->attachment_path)
                                        <a
                                            href="{{ route('student.leave.attachment', $req->id) }}"
                                            target="_blank"
                                            class="portal-btn portal-btn-secondary"
                                            style="padding: 4px 8px; font-size: 11.5px; text-decoration: none;"
                                        >
                                            <x-icon name="clipboard" size="13" /> View Doc
                                        </a>
                                    @else
                                        <span style="color: var(--portal-muted); font-size: 12px;">—</span>
                                    @endif
                                </td>
                                <td>
                                    <x-portal.status-badge :variant="$badgeVariant">
                                        {{ ucfirst($req->status) }}
                                    </x-portal.status-badge>
                                </td>
                                <td>
                                    @if($req->status === 'pending')
                                        <span style="color: #D97706; font-size: 12px; font-weight: 500;">Awaiting teacher review</span>
                                    @else
                                        <div style="font-size: 12px;">
                                            @if($req->reviewer)
                                                <div><strong>{{ $req->reviewer->name }}</strong></div>
                                            @endif
                                            @if($req->review_note)
                                                <div style="color: var(--portal-muted); font-style: italic; margin-top: 2px;">"{{ $req->review_note }}"</div>
                                            @endif
                                            @if($req->reviewed_at)
                                                <div style="font-size: 11px; color: #94A3B8;">{{ $req->reviewed_at->format('d M Y') }}</div>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($req->status === 'pending')
                                        <form method="POST" action="{{ route('student.leave.cancel', $req->id) }}" class="portal-cancel-leave-form" onsubmit="return confirm('Are you sure you want to cancel this pending leave request?');">
                                            @csrf
                                            @method('PATCH')
                                            <button
                                                type="submit"
                                                class="portal-btn"
                                                style="color: var(--portal-danger); border: 1px solid var(--portal-border); background: #FFFFFF; padding: 4px 10px; font-size: 12px;"
                                            >
                                                Cancel
                                            </button>
                                        </form>
                                    @else
                                        <span style="color: var(--portal-muted); font-size: 12px;">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- "Apply for Leave" Modal -->
<x-portal.modal id="apply-leave-modal" title="Apply for Student Leave">
    <form id="portal-apply-leave-form" method="POST" action="{{ route('student.leave.store') }}" enctype="multipart/form-data">
        @csrf
        <div id="leave-form-general-error" class="portal-form-error" style="display: none; margin-bottom: 12px; padding: 10px 14px; background: #FEF2F2; border-radius: 6px; border: 1px solid #FECACA;"></div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="portal-form-group">
                <label class="portal-form-label" for="leave-from-date">From Date *</label>
                <input
                    type="date"
                    id="leave-from-date"
                    name="from_date"
                    class="portal-form-input"
                    min="{{ now()->toDateString() }}"
                    value="{{ old('from_date', now()->toDateString()) }}"
                    required
                />
                <div class="portal-form-error" id="error-from_date"></div>
            </div>

            <div class="portal-form-group">
                <label class="portal-form-label" for="leave-to-date">To Date *</label>
                <input
                    type="date"
                    id="leave-to-date"
                    name="to_date"
                    class="portal-form-input"
                    min="{{ now()->toDateString() }}"
                    value="{{ old('to_date', now()->toDateString()) }}"
                    required
                />
                <div class="portal-form-error" id="error-to_date"></div>
            </div>
        </div>

        <div class="portal-form-group">
            <label class="portal-form-label" for="leave-reason">Reason for Absence (10 – 500 characters) *</label>
            <textarea
                id="leave-reason"
                name="reason"
                class="portal-form-textarea"
                rows="4"
                minlength="10"
                maxlength="500"
                placeholder="Please describe reason for leave (medical, family event, emergency, etc.)..."
                required
            >{{ old('reason') }}</textarea>
            <div style="display: flex; justify-content: space-between; margin-top: 4px;">
                <div class="portal-form-error" id="error-reason"></div>
                <span id="reason-char-count" style="font-size: 11px; color: var(--portal-muted);">0 / 500</span>
            </div>
        </div>

        <div class="portal-form-group">
            <label class="portal-form-label" for="leave-attachment">Supporting Document / Medical Note (Optional)</label>
            <input
                type="file"
                id="leave-attachment"
                name="attachment"
                class="portal-form-input"
                accept=".pdf,.jpg,.jpeg,.png"
                style="padding: 7px 10px;"
            />
            <span style="display: block; font-size: 11.5px; color: var(--portal-muted); margin-top: 4px;">
                Accepted formats: PDF, JPG, PNG (Max file size: 2 MB). Stored securely outside public directories.
            </span>
            <div class="portal-form-error" id="error-attachment"></div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--portal-border);">
            <button type="button" class="portal-btn portal-btn-secondary" data-portal-modal-close>
                Cancel
            </button>
            <button type="submit" id="submit-leave-btn" class="portal-btn portal-btn-primary">
                <span id="submit-leave-text">Submit Leave Request</span>
            </button>
        </div>
    </form>
</x-portal.modal>

<!-- Mobile Bottom Sheet / Modal for Day Tap Details -->
<x-portal.modal id="day-detail-modal" title="Day Attendance Details">
    <div id="day-detail-content" style="font-size: 14px; line-height: 1.6;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <span id="day-detail-date" style="font-weight: 700; font-size: 16px; color: var(--portal-text);">Date</span>
            <span id="day-detail-status-badge" class="portal-badge info">Status</span>
        </div>
        <div id="day-detail-holiday-box" style="display: none; padding: 10px 14px; background: #F1F5F9; border-radius: 6px; margin-bottom: 12px;">
            <strong>Holiday:</strong> <span id="day-detail-holiday-name"></span>
        </div>
        <div id="day-detail-remarks-box" style="display: none; padding: 10px 14px; background: #FEF3C7; border-radius: 6px; margin-bottom: 12px; color: #92400E;">
            <strong>Teacher Remarks:</strong> <span id="day-detail-remarks-text"></span>
        </div>
    </div>
</x-portal.modal>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Month Picker Event Listener
    const monthPicker = document.getElementById('portal-month-input-picker');
    if (monthPicker) {
        monthPicker.addEventListener('change', (e) => {
            const selectedMonth = e.target.value;
            if (selectedMonth) {
                window.location.href = `{{ route('portal.attendance') }}?month=${selectedMonth}`;
            }
        });
    }

    // 2. Character counter for reason textarea
    const reasonTextarea = document.getElementById('leave-reason');
    const charCountDisplay = document.getElementById('reason-char-count');
    if (reasonTextarea && charCountDisplay) {
        const updateCount = () => {
            charCountDisplay.textContent = `${reasonTextarea.value.length} / 500`;
        };
        reasonTextarea.addEventListener('input', updateCount);
        updateCount();
    }

    // 3. AJAX Submission for Apply for Leave Modal
    const applyForm = document.getElementById('portal-apply-leave-form');
    const submitBtn = document.getElementById('submit-leave-btn');
    const submitText = document.getElementById('submit-leave-text');
    const generalError = document.getElementById('leave-form-general-error');

    if (applyForm) {
        applyForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Clear previous errors
            document.querySelectorAll('.portal-form-error').forEach(el => {
                el.textContent = '';
                el.style.display = 'none';
            });
            generalError.style.display = 'none';

            // Client-side quick check
            const fromVal = document.getElementById('leave-from-date').value;
            const toVal = document.getElementById('leave-to-date').value;
            const reasonVal = reasonTextarea.value.trim();

            if (!fromVal || !toVal) {
                const err = document.getElementById('error-from_date');
                err.textContent = 'Please choose both from and to dates.';
                err.style.display = 'block';
                return;
            }

            if (new Date(toVal) < new Date(fromVal)) {
                const err = document.getElementById('error-to_date');
                err.textContent = 'To date must be greater than or equal to from date.';
                err.style.display = 'block';
                return;
            }

            if (reasonVal.length < 10) {
                const err = document.getElementById('error-reason');
                err.textContent = 'Reason must be at least 10 characters long.';
                err.style.display = 'block';
                return;
            }

            // Disable submit button during request
            submitBtn.disabled = true;
            submitText.textContent = 'Submitting...';

            const formData = new FormData(applyForm);

            try {
                const response = await fetch("{{ route('student.leave.store') }}", {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });

                const data = await response.json();

                if (!response.ok) {
                    if (response.status === 422 && data.errors) {
                        for (const [field, messages] of Object.entries(data.errors)) {
                            const errEl = document.getElementById(`error-${field}`);
                            if (errEl) {
                                errEl.textContent = messages[0];
                                errEl.style.display = 'block';
                            } else {
                                generalError.textContent = messages[0];
                                generalError.style.display = 'block';
                            }
                        }
                    } else {
                        generalError.textContent = data.message || 'An error occurred while submitting leave request.';
                        generalError.style.display = 'block';
                    }
                    submitBtn.disabled = false;
                    submitText.textContent = 'Submit Leave Request';
                    return;
                }

                // Success! Close modal and show toast
                document.getElementById('apply-leave-modal').classList.remove('open');
                applyForm.reset();

                // Append notification toast
                showPortalToast('Leave request submitted successfully! Reloading page to update records...', 'success');

                setTimeout(() => {
                    window.location.reload();
                }, 1200);

            } catch (err) {
                generalError.textContent = 'Network or server error. Please try again.';
                generalError.style.display = 'block';
                submitBtn.disabled = false;
                submitText.textContent = 'Submit Leave Request';
            }
        });
    }
});

// Helper for mobile or desktop day click
function handleCalendarDayClick(element) {
    const date = element.getAttribute('data-date');
    const status = element.getAttribute('data-status');
    const holiday = element.getAttribute('data-holiday');
    const remarks = element.getAttribute('data-remarks');

    const modal = document.getElementById('day-detail-modal');
    if (!modal) return;

    document.getElementById('day-detail-date').textContent = new Date(date).toLocaleDateString(undefined, {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });

    const badge = document.getElementById('day-detail-status-badge');
    badge.textContent = status;
    badge.className = `portal-badge ${status.toLowerCase() === 'present' ? 'success' : (status.toLowerCase() === 'absent' ? 'danger' : (status.toLowerCase() === 'late' ? 'warning' : 'info'))}`;

    const holidayBox = document.getElementById('day-detail-holiday-box');
    if (holiday) {
        document.getElementById('day-detail-holiday-name').textContent = holiday;
        holidayBox.style.display = 'block';
    } else {
        holidayBox.style.display = 'none';
    }

    const remarksBox = document.getElementById('day-detail-remarks-box');
    if (remarks) {
        document.getElementById('day-detail-remarks-text').textContent = remarks;
        remarksBox.style.display = 'block';
    } else {
        remarksBox.style.display = 'none';
    }

    modal.classList.add('open');
}

// Show dynamic Toast helper
function showPortalToast(message, type = 'success') {
    const container = document.getElementById('portal-toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `portal-toast ${type}`;
    toast.style.animation = 'portalFadeIn 0.3s ease-out';
    toast.innerHTML = `
        <div class="portal-toast-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="portal-toast-content" style="flex: 1;">${message}</div>
    `;

    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 300);
    }, 4500);
}
</script>
@endsection

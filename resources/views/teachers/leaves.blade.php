@extends('layouts.admin')
@section('title','Teacher Leaves')
@section('content')
<div class="page-heading">
    <div>
        <div class="breadcrumb">Leaves <span>/</span> Teacher Leaves</div>
        <h1>Teacher Leaves</h1>
        <p>Review requests, track balances and plan your teaching cover.</p>
    </div>
</div>

@include('students.partials.feedback')

<!-- Enhanced Filter Card (Month, Teacher, Status, View) -->
<section class="panel teacher-leaves-filter-card" aria-labelledby="filter-heading">
    <div class="filter-card-header">
        <h2 id="filter-heading" class="filter-card-title">Filter Leave Requests</h2>
        <span class="active-filters-pill" role="status">
            Showing: {{ $activeFiltersText }}
        </span>
    </div>

    <form id="teacher-leaves-filter-form" method="GET" action="{{ route('teachers.leaves.index') }}" data-auto-filters>
        <div class="filter-controls-grid">
            <!-- 1. Month Control -->
            <div class="filter-control-group">
                <div class="control-label-row">
                    <label for="leave-month" class="filter-label">Month</label>
                    <button type="button" class="btn-this-month" id="btn-this-month" data-current-month="{{ $thisMonth }}">This month</button>
                </div>
                <div class="month-input-wrapper">
                    <button type="button" id="btn-prev-month" class="btn-month-nav" aria-label="Previous month" title="Previous month" data-target-month="{{ $prevMonth }}">
                        <x-icon name="chevron-left" size="16" />
                    </button>
                    <input id="leave-month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="filter-input month-picker-field" required>
                    <button type="button" id="btn-next-month" class="btn-month-nav" aria-label="Next month" title="Next month" data-target-month="{{ $nextMonth }}">
                        <x-icon name="chevron-right" size="16" />
                    </button>
                </div>
                <div class="control-helper-text" id="month-range-helper">{{ $dateRangeText }}</div>
            </div>

            <!-- 2. Teacher Control -->
            <div class="filter-control-group">
                <label for="leave-teacher-filter" class="filter-label">Teacher</label>
                <select id="leave-teacher-filter" name="teacher" class="filter-select">
                    <option value="">All teachers</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected((string)$selectedTeacherId === (string)$teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
                <div class="control-helper-text">Select a teacher to inspect their annual balance</div>
            </div>

            <!-- 3. Status Control -->
            <div class="filter-control-group">
                <label for="leave-status" class="filter-label">Status</label>
                <select id="leave-status" name="status" class="filter-select">
                    <option value="All" @selected($selectedStatus === 'All')>All ({{ $statusCounts['All'] }})</option>
                    <option value="Pending" @selected($selectedStatus === 'Pending')>Pending ({{ $statusCounts['Pending'] }})</option>
                    <option value="Approved" @selected($selectedStatus === 'Approved')>Approved ({{ $statusCounts['Approved'] }})</option>
                    <option value="Rejected" @selected($selectedStatus === 'Rejected')>Rejected ({{ $statusCounts['Rejected'] }})</option>
                    <option value="Cancelled" @selected($selectedStatus === 'Cancelled')>Cancelled ({{ $statusCounts['Cancelled'] }})</option>
                </select>
                <div class="control-helper-text">Filter by request decision status</div>
            </div>

            <!-- 4. View Control -->
            <div class="filter-control-group">
                <label for="leave-view" class="filter-label">View</label>
                <select id="leave-view" name="view" class="filter-select">
                    <option value="list" @selected($view === 'list')>List view</option>
                    <option value="calendar" @selected($view === 'calendar')>Calendar view</option>
                </select>
                <div class="control-helper-text">Calendar shows who is on leave each day</div>
            </div>
        </div>

        <div class="filter-actions-row">
            <a href="{{ route('teachers.leaves.index') }}" class="btn-filter-reset">Reset</a>
            <button type="submit" class="btn-filter-apply">Apply</button>
        </div>
    </form>

    <!-- Teacher Remaining Balance Summary Card (when a single teacher is selected) -->
    @if($selectedTeacher && !empty($teacherBalancesSummary))
        <div class="teacher-balance-summary-card" role="region" aria-label="Teacher Leave Balance">
            <div class="summary-card-header">
                <strong class="summary-card-title">Leave Balances &middot; {{ $selectedTeacher->name }} ({{ $month->year }})</strong>
            </div>
            <div class="summary-card-badges">
                @foreach($teacherBalancesSummary as $item)
                    <span class="balance-badge-item {{ $item['is_no_limit'] ? 'badge-no-limit' : '' }}">
                        <strong>{{ $item['type'] }}:</strong> {{ $item['text'] }}
                    </span>
                @endforeach
            </div>
            <p class="summary-card-note">Pending and approved requests reserve calendar days. Weekends count toward leave.</p>
        </div>
    @endif
</section>

<!-- New leave request form -->
<section class="panel student-form leave-request-form">
    <div class="panel-heading">
        <div>
            <h2>New leave request</h2>
            <p>Select a teacher, leave type, dates, and reason.</p>
        </div>
    </div>
    <form method="POST" action="{{ route('teachers.leaves.store') }}">
        @csrf
        <div class="student-form-grid">
            <div class="admission-field">
                <label for="apply-teacher">Teacher</label>
                <select id="apply-teacher" name="teacher_id" required>
                    <option value="">Select teacher</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected((string)old('teacher_id', $selectedTeacherId) === (string)$teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="admission-field">
                <label for="apply-type">Leave type</label>
                <select id="apply-type" name="leave_type_id" required>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" @selected((string)old('leave_type_id') === (string)$type->id)>{{ $type->name }} ({{ $type->is_paid ? 'paid' : 'unpaid' }})</option>
                    @endforeach
                </select>
            </div>
            @foreach(['start_date' => 'From', 'end_date' => 'To'] as $field => $label)
                <div class="admission-field">
                    <label for="leave-{{ $field }}">{{ $label }}</label>
                    <input id="leave-{{ $field }}" name="{{ $field }}" type="date" value="{{ old($field) }}" required>
                </div>
            @endforeach
            <div class="admission-field">
                <label for="leave-reason">Reason</label>
                <textarea id="leave-reason" name="reason" maxlength="2000" required>{{ old('reason') }}</textarea>
            </div>
        </div>
        <div class="form-actions">
            <button class="button-primary">Submit Request</button>
        </div>
    </form>
</section>

<!-- Content Section (Calendar or List) -->
@if($view === 'calendar')
    <section class="panel student-form">
        <div class="panel-heading">
            <h2>Calendar View &middot; {{ $month->format('F Y') }}</h2>
        </div>
        @if($calendar->isEmpty())
            <div class="calendar-empty-banner">
                <p>No leave requests found for these filters in {{ $month->format('F Y') }}. <a href="{{ route('teachers.leaves.index') }}">Reset filters</a></p>
            </div>
        @endif
        <div class="teacher-calendar">
            @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)
                <strong>{{ $day }}</strong>
            @endforeach
            @for($i = 1; $i < $month->isoWeekday(); $i++)
                <div aria-hidden="true"></div>
            @endfor
            @for($i = 1; $i <= $month->daysInMonth; $i++)
                @php($date = $month->copy()->day($i))
                <div class="teacher-calendar-day">
                    <strong>{{ $i }}</strong>
                    @foreach($calendar as $leave)
                        @if($date->betweenIncluded($leave->start_date, $leave->end_date))
                            <span class="teacher-calendar-event badge-{{ strtolower($leave->status) }}">
                                {{ $leave->teacher->name }} &middot; {{ $leave->status }}
                            </span>
                        @endif
                    @endforeach
                </div>
            @endfor
        </div>
    </section>
@else
    <section class="panel">
        <div class="panel-heading">
            <h2>Leave requests &middot; {{ $month->format('F Y') }}</h2>
        </div>
        <div class="student-table-scroll">
            <table class="student-table">
                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Review</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leaves as $leave)
                        <tr>
                            <td><strong>{{ $leave->teacher->name }}</strong></td>
                            <td>{{ $leave->leaveType->name }}</td>
                            <td>{{ $leave->start_date->format('d M') }} &ndash; {{ $leave->end_date->format('d M Y') }}</td>
                            <td>{{ $leave->reason }}</td>
                            <td>
                                <span class="student-status badge-{{ strtolower($leave->status) }}">{{ $leave->status }}</span>
                            </td>
                            <td>
                                @if($leave->status === 'Pending')
                                    <form method="POST" action="{{ route('teachers.leaves.review', $leave) }}" class="teacher-review">
                                        @csrf
                                        <label class="sr-only" for="remarks-{{ $leave->id }}">Review remarks</label>
                                        <input id="remarks-{{ $leave->id }}" name="remarks" required maxlength="2000" placeholder="Remarks (required)">
                                        <div class="student-actions">
                                            <button class="action-view" name="status" value="Approved">Approve</button>
                                            <button class="action-delete" name="status" value="Rejected">Reject</button>
                                        </div>
                                    </form>
                                @else
                                    <span style="font-size: 13px; color: #64748b;">{{ $leave->remarks ?: 'No remarks' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="leave-empty-state">
                                    <div class="empty-state-icon-wrap"><x-icon name="calendar" size="28" /></div>
                                    <h3>No leave requests found for these filters</h3>
                                    <p>There are no leave records matching <strong>{{ $activeFiltersText }}</strong>.</p>
                                    <a href="{{ route('teachers.leaves.index') }}" class="btn-filter-reset">Reset filters</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="student-pagination">
            {{ $leaves->links() }}
        </div>
    </section>
@endif

@include('teachers.partials.setup')

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('teacher-leaves-filter-form');
    if (!form) return;

    const monthInput = document.getElementById('leave-month');
    const teacherSelect = document.getElementById('leave-teacher-filter');
    const statusSelect = document.getElementById('leave-status');
    const viewSelect = document.getElementById('leave-view');
    const prevBtn = document.getElementById('btn-prev-month');
    const nextBtn = document.getElementById('btn-next-month');
    const thisMonthBtn = document.getElementById('btn-this-month');

    // Auto-apply on dropdown changes and store view preference
    [teacherSelect, statusSelect, viewSelect].forEach(function (el) {
        if (el) {
            el.addEventListener('change', function () {
                if (el === viewSelect) {
                    try { localStorage.setItem('teacher_leaves_view', el.value); } catch (e) {}
                }
                form.requestSubmit();
            });
        }
    });

    // Auto-apply on month change
    if (monthInput) {
        monthInput.addEventListener('change', function () {
            form.requestSubmit();
        });
    }

    // Previous month navigation
    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (prevBtn.dataset.targetMonth) {
                monthInput.value = prevBtn.dataset.targetMonth;
                form.requestSubmit();
            }
        });
    }

    // Next month navigation
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (nextBtn.dataset.targetMonth) {
                monthInput.value = nextBtn.dataset.targetMonth;
                form.requestSubmit();
            }
        });
    }

    // "This month" shortcut
    if (thisMonthBtn) {
        thisMonthBtn.addEventListener('click', function () {
            if (thisMonthBtn.dataset.currentMonth) {
                monthInput.value = thisMonthBtn.dataset.currentMonth;
                form.requestSubmit();
            }
        });
    }

    // Restore view preference from localStorage if not specified in URL query
    if (viewSelect) {
        try {
            const storedView = localStorage.getItem('teacher_leaves_view');
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('view') && storedView && (storedView === 'list' || storedView === 'calendar') && storedView !== viewSelect.value) {
                viewSelect.value = storedView;
                form.requestSubmit();
            }
        } catch (e) {}
    }
});
</script>
@endsection

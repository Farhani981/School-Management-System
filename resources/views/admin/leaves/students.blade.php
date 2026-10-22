@extends($layout)
@section('title', 'Student Leave Requests')

@section('content')
<div class="page-heading">
    <div>
        <div class="breadcrumb">
            <a href="{{ $isAdmin ? route('dashboard') : route('teacher.dashboard') }}">Home</a>
            <span>/</span> Attendance
            <span>/</span> Student Leaves
        </div>
        <h1>Student Leave Requests</h1>
        <p>Review and decide on leave applications submitted by students and parents.</p>
    </div>
</div>

@if(session('success'))
    <div style="background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500;">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500;">
        {{ session('error') }}
    </div>
@endif

<!-- Summary Metric Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="panel" style="padding: 16px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <span style="font-size: 12px; color: #64748B; text-transform: uppercase; font-weight: 600;">Pending Review</span>
            <div style="font-size: 26px; font-weight: 700; color: #D97706; margin-top: 4px;">{{ $pendingCount }}</div>
        </div>
        <div style="width: 44px; height: 44px; border-radius: 10px; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center;">
            <x-icon name="clock" size="22" />
        </div>
    </div>

    <div class="panel" style="padding: 16px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <span style="font-size: 12px; color: #64748B; text-transform: uppercase; font-weight: 600;">Approved</span>
            <div style="font-size: 26px; font-weight: 700; color: #059669; margin-top: 4px;">{{ $approvedCount }}</div>
        </div>
        <div style="width: 44px; height: 44px; border-radius: 10px; background: #D1FAE5; color: #059669; display: flex; align-items: center; justify-content: center;">
            <x-icon name="check" size="22" />
        </div>
    </div>

    <div class="panel" style="padding: 16px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <span style="font-size: 12px; color: #64748B; text-transform: uppercase; font-weight: 600;">Rejected</span>
            <div style="font-size: 26px; font-weight: 700; color: #DC2626; margin-top: 4px;">{{ $rejectedCount }}</div>
        </div>
        <div style="width: 44px; height: 44px; border-radius: 10px; background: #FEE2E2; color: #DC2626; display: flex; align-items: center; justify-content: center;">
            <x-icon name="help" size="22" />
        </div>
    </div>
</div>

<!-- Filters Bar -->
<section class="panel student-form" style="margin-bottom: 24px;">
    <form method="GET" action="{{ url()->current() }}" class="student-form-grid" style="align-items: flex-end;">
        <div class="admission-field">
            <label for="filter-status">Status</label>
            <select id="filter-status" name="status">
                <option value="">All Statuses</option>
                <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
            </select>
        </div>

        <div class="admission-field">
            <label for="filter-class">Class</label>
            <select id="filter-class" name="class_id">
                <option value="">All Classes</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}" @selected((string)request('class_id') === (string)$c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="admission-field">
            <label for="filter-date">Date Overlap</label>
            <input id="filter-date" name="date" type="date" value="{{ request('date') }}">
        </div>

        <div class="admission-field">
            <label for="filter-search">Student Search</label>
            <input id="filter-search" name="search" type="text" placeholder="Name, Roll or Admission No..." value="{{ request('search') }}">
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="button-primary">Filter</button>
            <a href="{{ url()->current() }}" class="button-secondary" style="text-decoration: none;">Reset</a>
        </div>
    </form>
</section>

<!-- Leave Requests Table -->
<section class="panel">
    <div class="panel-heading">
        <h2>Leave Requests ({{ $leaves->total() }})</h2>
    </div>

    <div class="student-table-scroll">
        <table class="student-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Class / Sec</th>
                    <th>Date Range</th>
                    <th>Reason</th>
                    <th>Attachment</th>
                    <th>Status</th>
                    <th>Action / Review</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leaves as $leave)
                    @php
                        $reviewRoute = $isAdmin
                            ? route('admin.student-leaves.update', $leave->id)
                            : route('teacher.student-leaves.update', $leave->id);
                        $attachmentRoute = $isAdmin
                            ? route('admin.student-leaves.attachment', $leave->id)
                            : route('teacher.student-leaves.attachment', $leave->id);
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $leave->student?->full_name ?? 'Unknown Student' }}</strong>
                            <div style="font-size: 11px; color: #64748B;">
                                Roll: {{ $leave->student?->roll_number ?: 'N/A' }} &middot; Adm: {{ $leave->student?->admission_no }}
                            </div>
                        </td>
                        <td>
                            {{ $leave->student?->schoolClass?->name ?? '—' }}
                            @if($leave->student?->section)
                                ({{ $leave->student->section->name }})
                            @endif
                        </td>
                        <td>
                            <strong>{{ $leave->from_date?->format('d M Y') }}</strong> &rarr; <strong>{{ $leave->to_date?->format('d M Y') }}</strong>
                            <div style="font-size: 11px; color: #64748B;">{{ $leave->days_count }} {{ Str::plural('day', $leave->days_count) }}</div>
                        </td>
                        <td style="max-width: 260px;">
                            <div style="font-size: 13px; line-height: 1.4; word-break: break-word;">{{ $leave->reason }}</div>
                        </td>
                        <td>
                            @if($leave->attachment_path)
                                <a href="{{ $attachmentRoute }}" target="_blank" class="button-secondary" style="font-size: 11px; padding: 4px 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                    <x-icon name="clipboard" size="13" /> View Doc
                                </a>
                            @else
                                <span style="color: #94A3B8; font-size: 12px;">None</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $badgeStyle = match($leave->status) {
                                    'approved' => 'background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0;',
                                    'rejected' => 'background:#FEE2E2; color:#991B1B; border:1px solid #FECACA;',
                                    'pending' => 'background:#FEF3C7; color:#92400E; border:1px solid #FDE68A;',
                                    default => 'background:#F1F5F9; color:#475569; border:1px solid #E2E8F0;',
                                };
                            @endphp
                            <span style="display: inline-block; padding: 3px 8px; border-radius: 9999px; font-size: 11.5px; font-weight: 600; text-transform: capitalize; {{ $badgeStyle }}">
                                {{ $leave->status }}
                            </span>
                        </td>
                        <td>
                            @if($leave->status === 'pending')
                                <form method="POST" action="{{ $reviewRoute }}" style="display: flex; flex-direction: column; gap: 6px; min-width: 220px;">
                                    @csrf
                                    <input type="text" name="review_note" placeholder="Optional review note..." maxlength="500" style="font-size: 12px; padding: 6px 8px; border-radius: 6px; border: 1px solid #CBD5E1; width: 100%; box-sizing: border-box;">
                                    <div style="display: flex; gap: 6px;">
                                        <button type="submit" name="status" value="approved" class="button-primary" style="background: #059669; border-color: #059669; padding: 4px 10px; font-size: 12px; cursor: pointer;">
                                            Approve
                                        </button>
                                        <button type="submit" name="status" value="rejected" class="button-secondary" style="color: #DC2626; border-color: #FCA5A5; padding: 4px 10px; font-size: 12px; cursor: pointer;">
                                            Reject
                                        </button>
                                    </div>
                                </form>
                            @else
                                <div style="font-size: 12px; color: #475569;">
                                    @if($leave->reviewer)
                                        <div><strong>By:</strong> {{ $leave->reviewer->name }}</div>
                                    @endif
                                    @if($leave->review_note)
                                        <div style="font-style: italic; color: #64748B; margin-top: 2px;">"{{ $leave->review_note }}"</div>
                                    @endif
                                    @if($leave->reviewed_at)
                                        <div style="font-size: 11px; color: #94A3B8;">{{ $leave->reviewed_at->format('d M Y, h:i A') }}</div>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 36px 16px; color: #64748B;">
                            No student leave requests found matching your filter criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($leaves->hasPages())
        <div class="student-pagination" style="padding: 16px;">
            {{ $leaves->links() }}
        </div>
    @endif
</section>
@endsection

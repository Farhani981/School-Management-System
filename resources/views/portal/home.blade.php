@extends('layouts.portal')
@section('title', 'Student Portal Dashboard')

@section('content')
@php
    $feeStatus = $feeSummary['status'] ?? 'paid';
    $feeCardValue = match($feeStatus) {
        'paid' => 'Paid',
        'pending' => 'Rs. ' . number_format($feeSummary['totalPending'] ?? 0),
        'overdue' => 'Rs. ' . number_format($feeSummary['overdueAmount'] ?? 0),
        default => 'Paid',
    };
    $feeCardColor = match($feeStatus) {
        'paid' => 'success',
        'pending' => 'warning',
        'overdue' => 'danger',
        default => 'success',
    };
    $feeCardSubtext = match($feeStatus) {
        'paid' => 'No dues pending',
        'pending' => 'Due: ' . (!empty($feeSummary['nextDueDate']) ? \Carbon\Carbon::parse($feeSummary['nextDueDate'])->format('d M') : 'Pending'),
        'overdue' => 'Immediate payment due',
        default => 'Up to date',
    };
    $latestChallan = $feeSummary['latest'] ?? null;
@endphp

<div class="portal-page-header">
    <div>
        <h1 class="portal-page-title">Welcome back, {{ $student ? $student->first_name : auth()->user()->name }} 👋</h1>
        <p class="portal-page-subtitle">
            @if($student)
                Class {{ $student->schoolClass?->name ?? 'N/A' }} {{ $student->section?->name ? '('.$student->section->name.')' : '' }} &middot; Admission No: <strong>{{ $student->admission_no }}</strong> &middot; Roll No: <strong>{{ $student->roll_number ?? 'N/A' }}</strong>
            @else
                Portal Overview & Navigation
            @endif
        </p>
    </div>
    <div>
        <x-portal.status-badge variant="success">Active Enrollment</x-portal.status-badge>
    </div>
</div>

<!-- Quick Metrics Overview -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 28px;">
    <x-portal.stat-card
        title="Attendance Rate"
        value="94.2%"
        icon="calendar"
        color="success"
        trend="+2.1%"
        trendType="positive"
        subtext="this term"
    />

    <x-portal.stat-card
        title="Upcoming Quizzes"
        value="3"
        icon="clipboard"
        color="primary"
        subtext="2 due this week"
    />

    <x-portal.stat-card
        title="Term GPA / Grade"
        value="A-"
        icon="award"
        color="info"
        trend="Top 10%"
        trendType="positive"
        subtext="in Class"
    />

    <x-portal.stat-card
        title="Fee Clearance"
        :value="$feeCardValue"
        icon="wallet"
        :color="$feeCardColor"
        :subtext="$feeCardSubtext"
    />
</div>

<!-- Task 3: Dashboard Fee Challan Card with Real Data & PDF Download -->
@if($latestChallan)
    <div class="portal-card" style="background: white; border: 1px solid var(--portal-border); border-radius: var(--portal-radius); padding: 24px; margin-bottom: 28px; box-shadow: var(--portal-shadow);" id="dashboard-fee-challan-card">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: {{ $latestChallan['status'] === 'overdue' ? 'var(--portal-danger-light)' : 'var(--portal-warning-light)' }}; color: {{ $latestChallan['status'] === 'overdue' ? 'var(--portal-danger)' : 'var(--portal-warning)' }}; display: flex; align-items: center; justify-content: center;">
                    <x-icon name="wallet" size="24" />
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--portal-text);">Current Fee Challan &middot; {{ $latestChallan['month'] }}</h3>
                    </div>
                    <p style="margin: 3px 0 0; font-size: 13px; color: var(--portal-muted);">
                        Challan No: <strong>{{ $latestChallan['challan_no'] }}</strong> &middot; Due Date: <strong>{{ \Carbon\Carbon::parse($latestChallan['due_date'])->format('d M Y') }}</strong>
                    </p>
                </div>
            </div>
            <div>
                @if($latestChallan['status'] === 'overdue')
                    <x-portal.status-badge variant="danger">Overdue</x-portal.status-badge>
                @else
                    <x-portal.status-badge variant="warning">Pending Payment</x-portal.status-badge>
                @endif
            </div>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; padding-top: 16px; border-top: 1px solid var(--portal-border-subtle);">
            <div>
                <span style="font-size: 12px; color: var(--portal-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Outstanding Amount</span>
                <div style="font-size: 26px; font-weight: 800; color: {{ $latestChallan['status'] === 'overdue' ? 'var(--portal-danger)' : 'var(--portal-text)' }};">
                    Rs. {{ number_format($latestChallan['amount']) }}
                </div>
            </div>
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <a href="{{ route('student.fees.challans.pdf', $latestChallan['id']) }}" target="_blank" class="portal-btn portal-btn-primary" id="dashboard-download-challan-btn">
                    <x-icon name="download" size="18" />
                    <span>Download Challan (PDF)</span>
                </a>
                <a href="{{ route('portal.fees') }}" class="portal-btn portal-btn-secondary">
                    <span>View Fees &amp; Billing</span>
                </a>
            </div>
        </div>
    </div>
@else
    <div class="portal-card" style="background: white; border: 1px solid var(--portal-border); border-radius: var(--portal-radius); padding: 20px 24px; margin-bottom: 28px; box-shadow: var(--portal-shadow); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;" id="dashboard-fee-cleared-card">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: var(--portal-success-light); color: var(--portal-success); display: flex; align-items: center; justify-content: center;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
            </div>
            <div>
                <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--portal-text);">All Fees Cleared</h4>
                <p style="margin: 2px 0 0; font-size: 13px; color: var(--portal-muted);">No pending fee vouchers or overdue dues for this session.</p>
            </div>
        </div>
        <div>
            <a href="{{ route('portal.fees') }}" class="portal-btn portal-btn-secondary">
                <span>View Fee Ledger &amp; Receipts</span>
            </a>
        </div>
    </div>
@endif

<!-- Module Placeholders Navigation Notice -->
<x-portal.empty-state
    title="Dashboard Content Coming Soon"
    description="The complete student portal dashboard modules, analytics charts, class schedules, and interactive gradebook are currently in development. You can navigate through all sections using the sidebar or bottom bar."
    icon="dashboard"
    actionText="Explore Profile"
    :actionUrl="route('portal.profile')"
    badge="Under Development"
/>
@endsection

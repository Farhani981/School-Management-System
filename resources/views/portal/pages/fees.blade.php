@extends('layouts.portal')
@section('title', 'Fees & Billing')

@section('content')
@php
    $statusVariant = match($summary['status']) {
        'paid' => 'success',
        'pending' => 'warning',
        'overdue' => 'danger',
        default => 'info'
    };
    $statusLabel = match($summary['status']) {
        'paid' => 'All Fees Paid',
        'pending' => 'Pending Dues',
        'overdue' => 'Payment Overdue',
        default => 'Status Unknown'
    };

    $totalPaid = $summary['totalPaidThisSession'] ?? 0;
    $totalPending = $summary['totalPending'] ?? 0;
    $grandTotal = $totalPaid + $totalPending;
    $paidPercent = $grandTotal > 0 ? round(($totalPaid / $grandTotal) * 100) : 100;
    $pendingPercent = 100 - $paidPercent;

    $latestChallan = $summary['latest'] ?? null;
@endphp

<!-- Toast Notification Container -->
<div id="portal-toast-container" style="position: fixed; top: 24px; right: 24px; z-index: 1000; display: flex; flex-direction: column; gap: 8px;">
    @if(session('success'))
        <x-portal.toast type="success" :message="session('success')" />
    @endif
    @if(session('error'))
        <x-portal.toast type="danger" :message="session('error')" />
    @endif
</div>

<!-- Page Header & Session Filter -->
<div class="portal-page-header">
    <div>
        <h1 class="portal-page-title">Fees &amp; Billing</h1>
        <p class="portal-page-subtitle">
            Showing fee records for <strong>{{ $student->full_name }}</strong> &middot; Admission No: <strong>{{ $student->admission_no }}</strong> &middot; Class {{ $student->schoolClass?->name ?? '—' }} ({{ $student->section?->name ?? '—' }})
        </p>
    </div>

    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <!-- Academic Session Selector -->
        <div style="display: flex; align-items: center; gap: 8px;">
            <label for="fee-session-select" style="font-size: 13px; font-weight: 600; color: var(--portal-muted);">Session:</label>
            <select
                id="fee-session-select"
                class="portal-select-input"
                aria-label="Select Academic Session"
                onchange="window.location.href = '{{ route('portal.fees') }}?session=' + this.value"
            >
                <option value="" {{ empty($selectedSession) ? 'selected' : '' }}>All Sessions</option>
                @foreach($sessions as $sess)
                    <option value="{{ $sess->id }}" {{ (string)$selectedSession === (string)$sess->id ? 'selected' : '' }}>
                        {{ $sess->name }}{{ $sess->is_current ? ' (Current)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <button type="button" class="portal-btn portal-btn-secondary" onclick="window.location.reload()" title="Refresh records">
            <x-icon name="rotate" size="16" />
            <span class="desktop-only">Refresh</span>
        </button>
    </div>
</div>

<!-- Warning Banners -->
@if($summary['status'] === 'overdue' && $summary['overdueAmount'] > 0)
    <div class="portal-warning-banner portal-warning-danger" role="alert" id="fee-overdue-banner">
        <div class="portal-warning-icon" style="background: var(--portal-danger-light); color: var(--portal-danger);">
            <x-icon name="bell" size="20" />
        </div>
        <div style="flex: 1;">
            <h4 class="portal-warning-title" style="color: var(--portal-danger);">Overdue Fee Warning &middot; Rs. {{ number_format($summary['overdueAmount']) }} Outstanding</h4>
            <p class="portal-warning-text">
                Your fee voucher due date has passed. Please deposit the outstanding balance of <strong>Rs. {{ number_format($summary['overdueAmount']) }}</strong> immediately at the school accounts counter or designated bank branch to avoid additional late fines and examination restrictions.
            </p>
        </div>
    </div>
@elseif($summary['status'] === 'pending' && $summary['totalPending'] > 0)
    <div class="portal-warning-banner" role="alert" id="fee-pending-banner">
        <div class="portal-warning-icon">
            <x-icon name="bell" size="20" />
        </div>
        <div style="flex: 1;">
            <h4 class="portal-warning-title">Pending Fee Dues &middot; Rs. {{ number_format($summary['totalPending']) }}</h4>
            <p class="portal-warning-text">
                You have pending dues payable on or before <strong>{{ !empty($summary['nextDueDate']) ? \Carbon\Carbon::parse($summary['nextDueDate'])->format('d M Y') : 'the due date' }}</strong>. Download your printable bank challan below.
            </p>
        </div>
    </div>
@endif

<!-- Summary Metric Cards -->
<div class="portal-fee-stats-grid">
    <x-portal.stat-card
        title="Total Pending"
        value="Rs. {{ number_format($summary['totalPending']) }}"
        icon="wallet"
        :color="$summary['totalPending'] > 0 ? 'warning' : 'success'"
        :subtext="$summary['totalPending'] > 0 ? 'All uncancelled dues' : 'No balance due'"
    />

    <x-portal.stat-card
        title="Paid This Session"
        value="Rs. {{ number_format($summary['totalPaidThisSession']) }}"
        icon="award"
        color="success"
        subtext="Verified payments"
    />

    <x-portal.stat-card
        title="Overdue Amount"
        value="Rs. {{ number_format($summary['overdueAmount']) }}"
        icon="clock"
        :color="$summary['overdueAmount'] > 0 ? 'danger' : 'primary'"
        :subtext="$summary['overdueAmount'] > 0 ? 'Past due date' : 'No overdue dues'"
    />

    <div class="portal-stat-card">
        <div class="portal-stat-header">
            <span class="portal-stat-title">Next Due Date</span>
            <div class="portal-stat-icon-wrap info">
                <x-icon name="calendar" size="22" />
            </div>
        </div>
        <div class="portal-stat-body" style="display: flex; align-items: baseline; gap: 8px;">
            <span class="portal-stat-value" style="font-size: 20px;">
                {{ !empty($summary['nextDueDate']) ? \Carbon\Carbon::parse($summary['nextDueDate'])->format('d M Y') : 'All Cleared' }}
            </span>
        </div>
        <div class="portal-stat-footer" style="display: flex; align-items: center; justify-content: space-between;">
            <x-portal.status-badge :variant="$statusVariant">{{ $statusLabel }}</x-portal.status-badge>
        </div>
    </div>
</div>

<!-- Layout: Highlight Challan + Visual Breakdown Chart -->
<div class="portal-fee-highlight-row">
    <!-- Current Challan Highlight Card -->
    <div class="portal-card portal-highlight-challan-card" id="current-challan-highlight-box">
        @if($latestChallan)
            <div class="portal-highlight-header">
                <div>
                    <span class="portal-card-tag">Latest Unpaid Challan</span>
                    <h3 class="portal-highlight-title">{{ $latestChallan['month'] }} Fee Challan</h3>
                    <p class="portal-highlight-sub">
                        Challan No: <strong>{{ $latestChallan['challan_no'] }}</strong> &middot; Due Date: <strong>{{ \Carbon\Carbon::parse($latestChallan['due_date'])->format('d M Y') }}</strong>
                    </p>
                </div>
                <div>
                    @if($latestChallan['status'] === 'overdue')
                        <x-portal.status-badge variant="danger">Overdue</x-portal.status-badge>
                    @else
                        <x-portal.status-badge variant="warning">Pending</x-portal.status-badge>
                    @endif
                </div>
            </div>

            <div class="portal-highlight-body">
                <div class="portal-amount-box">
                    <span class="portal-amount-label">Net Payable Amount</span>
                    <span class="portal-amount-value {{ $latestChallan['status'] === 'overdue' ? 'danger' : '' }}">
                        Rs. {{ number_format($latestChallan['amount']) }}
                    </span>
                </div>

                <div class="portal-highlight-actions">
                    <a
                        href="{{ route('student.fees.challans.pdf', $latestChallan['id']) }}"
                        target="_blank"
                        class="portal-btn portal-btn-primary"
                        id="highlight-download-pdf-btn"
                        title="Download printable 3-copy bank challan"
                    >
                        <x-icon name="download" size="18" />
                        <span>Download PDF</span>
                    </a>
                    <button
                        type="button"
                        class="portal-btn portal-btn-secondary"
                        onclick="printChallan('{{ route('student.fees.challans.pdf', $latestChallan['id']) }}')"
                        id="highlight-print-challan-btn"
                    >
                        <x-icon name="printer" size="18" />
                        <span>Print</span>
                    </button>
                    <button
                        type="button"
                        class="portal-btn portal-btn-outline"
                        onclick="openChallanModal({{ $latestChallan['id'] }})"
                    >
                        <span>View Breakdown</span>
                    </button>
                </div>
            </div>
        @else
            <div class="portal-cleared-highlight-content">
                <div class="portal-cleared-icon-wrap">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
                <div>
                    <h3 class="portal-highlight-title">All Dues Cleared</h3>
                    <p class="portal-highlight-sub">There are no outstanding fee vouchers or overdue dues pending for this profile.</p>
                </div>
            </div>
        @endif
    </div>

    <!-- Fee Breakdown Visual Chart Card -->
    <div class="portal-card portal-chart-card">
        <h3 class="portal-chart-title">Session Fee Overview</h3>
        <p class="portal-chart-sub">Paid vs Outstanding balance ratio</p>

        <div class="portal-chart-visual-wrap">
            <!-- Circular Donut SVG -->
            <div class="portal-donut-wrap">
                <svg viewBox="0 0 36 36" class="portal-donut-svg">
                    <path class="donut-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path class="donut-segment" stroke-dasharray="{{ $paidPercent }}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                </svg>
                <div class="portal-donut-text">
                    <span class="portal-donut-number">{{ $paidPercent }}%</span>
                    <span class="portal-donut-label">Paid</span>
                </div>
            </div>

            <!-- Legend and Figures -->
            <div class="portal-chart-legend">
                <div class="portal-legend-item">
                    <span class="portal-legend-dot success"></span>
                    <div>
                        <div class="portal-legend-title">Paid Amount</div>
                        <div class="portal-legend-value">Rs. {{ number_format($totalPaid) }}</div>
                    </div>
                </div>
                <div class="portal-legend-item">
                    <span class="portal-legend-dot {{ $totalPending > 0 ? 'warning' : 'muted' }}"></span>
                    <div>
                        <div class="portal-legend-title">Outstanding</div>
                        <div class="portal-legend-value">Rs. {{ number_format($totalPending) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress bar visual -->
        <div class="portal-progress-bar-wrap">
            <div class="portal-progress-bar" style="width: {{ $paidPercent }}%;"></div>
        </div>
    </div>
</div>

<!-- Challans Ledger Section with Tabs -->
<div class="portal-ledger-section">
    <div class="portal-section-header">
        <div>
            <h2 class="portal-section-title">Fee Challans &amp; Vouchers</h2>
            <p class="portal-section-sub">Browse, inspect itemized fee heads, and download bank challans</p>
        </div>

        <!-- Interactive Tabs -->
        <div class="portal-tabs" role="tablist">
            <button
                type="button"
                class="portal-tab-btn active"
                id="tab-btn-all"
                onclick="switchChallanTab('all')"
                role="tab"
                aria-selected="true"
            >
                All Challans ({{ $allChallans->count() }})
            </button>
            <button
                type="button"
                class="portal-tab-btn"
                id="tab-btn-unpaid"
                onclick="switchChallanTab('unpaid')"
                role="tab"
                aria-selected="false"
            >
                Unpaid ({{ $unpaidChallans->count() }})
            </button>
            <button
                type="button"
                class="portal-tab-btn"
                id="tab-btn-paid"
                onclick="switchChallanTab('paid')"
                role="tab"
                aria-selected="false"
            >
                Paid ({{ $paidChallans->count() }})
            </button>
        </div>
    </div>

    <!-- TAB 1: ALL CHALLANS -->
    <div id="tab-pane-all" class="portal-tab-pane active">
        @if($allChallans->isEmpty())
            <x-portal.empty-state
                title="No Fee Records Yet"
                description="There are no fee vouchers generated for the selected session."
                icon="wallet"
            />
        @else
            <!-- Desktop DataTable -->
            <div class="portal-table-container desktop-only">
                <div class="portal-table-scroll">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Month / Period</th>
                                <th>Challan No</th>
                                <th>Total Payable</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($allChallans as $c)
                            <tr>
                                <td>
                                    <strong>{{ $c['month'] }}</strong>
                                </td>
                                <td>
                                    <span class="portal-mono-text">{{ $c['challan_no'] }}</span>
                                </td>
                                <td>
                                    <strong>Rs. {{ number_format($c['total'] + $c['late_fine'] + $c['arrears']) }}</strong>
                                    @if($c['discount'] > 0)
                                        <div style="font-size: 11px; color: var(--portal-success);">Disc: Rs. {{ number_format($c['discount']) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span style="color: var(--portal-success); font-weight: 600;">Rs. {{ number_format($c['paid_amount']) }}</span>
                                </td>
                                <td>
                                    <span style="color: {{ $c['balance'] > 0 ? 'var(--portal-danger)' : 'var(--portal-text)' }}; font-weight: 700;">
                                        Rs. {{ number_format($c['balance']) }}
                                    </span>
                                </td>
                                <td>
                                    <span style="color: {{ $c['status'] === 'overdue' ? 'var(--portal-danger)' : 'inherit' }}; font-weight: {{ $c['status'] === 'overdue' ? '700' : 'normal' }};">
                                        {{ \Carbon\Carbon::parse($c['due_date'])->format('d M Y') }}
                                    </span>
                                </td>
                                <td>
                                    @if($c['status'] === 'paid')
                                        <x-portal.status-badge variant="success">Paid</x-portal.status-badge>
                                    @elseif($c['status'] === 'overdue')
                                        <x-portal.status-badge variant="danger">Overdue</x-portal.status-badge>
                                    @elseif($c['status'] === 'partial')
                                        <x-portal.status-badge variant="info">Partial</x-portal.status-badge>
                                    @else
                                        <x-portal.status-badge variant="warning">Unpaid</x-portal.status-badge>
                                    @endif
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <button
                                            type="button"
                                            class="portal-btn-sm portal-btn-outline"
                                            onclick="openChallanModal({{ $c['id'] }})"
                                            title="View Fee Heads Breakdown"
                                        >
                                            <span>Details</span>
                                        </button>
                                        <a
                                            href="{{ route('student.fees.challans.pdf', $c['id']) }}"
                                            target="_blank"
                                            class="portal-btn-sm portal-btn-secondary"
                                            title="Download Challan PDF"
                                        >
                                            <x-icon name="download" size="14" />
                                            <span>PDF</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Stacked Cards -->
            <div class="portal-mobile-challans mobile-only">
                @foreach($allChallans as $c)
                    <div class="portal-mobile-challan-card" onclick="openChallanModal({{ $c['id'] }})">
                        <div class="portal-mobile-card-top">
                            <div>
                                <h4 class="portal-mobile-challan-title">{{ $c['month'] }}</h4>
                                <span class="portal-mono-text">{{ $c['challan_no'] }}</span>
                            </div>
                            <div>
                                @if($c['status'] === 'paid')
                                    <x-portal.status-badge variant="success">Paid</x-portal.status-badge>
                                @elseif($c['status'] === 'overdue')
                                    <x-portal.status-badge variant="danger">Overdue</x-portal.status-badge>
                                @elseif($c['status'] === 'partial')
                                    <x-portal.status-badge variant="info">Partial</x-portal.status-badge>
                                @else
                                    <x-portal.status-badge variant="warning">Unpaid</x-portal.status-badge>
                                @endif
                            </div>
                        </div>

                        <div class="portal-mobile-card-grid">
                            <div>
                                <span class="mobile-grid-label">Total Payable</span>
                                <span class="mobile-grid-val">Rs. {{ number_format($c['total'] + $c['late_fine'] + $c['arrears']) }}</span>
                            </div>
                            <div>
                                <span class="mobile-grid-label">Balance</span>
                                <span class="mobile-grid-val {{ $c['balance'] > 0 ? 'danger' : 'success' }}">Rs. {{ number_format($c['balance']) }}</span>
                            </div>
                            <div>
                                <span class="mobile-grid-label">Due Date</span>
                                <span class="mobile-grid-val">{{ \Carbon\Carbon::parse($c['due_date'])->format('d M Y') }}</span>
                            </div>
                            <div>
                                <span class="mobile-grid-label">Paid Date</span>
                                <span class="mobile-grid-val">{{ $c['paid_date'] ? \Carbon\Carbon::parse($c['paid_date'])->format('d M Y') : '—' }}</span>
                            </div>
                        </div>

                        <div class="portal-mobile-card-actions" onclick="event.stopPropagation();">
                            <button type="button" class="portal-btn-sm portal-btn-outline" onclick="openChallanModal({{ $c['id'] }})">
                                View Breakdown
                            </button>
                            <a href="{{ route('student.fees.challans.pdf', $c['id']) }}" target="_blank" class="portal-btn-sm portal-btn-primary">
                                <x-icon name="download" size="14" />
                                <span>PDF</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- TAB 2: UNPAID CHALLANS -->
    <div id="tab-pane-unpaid" class="portal-tab-pane">
        @if($unpaidChallans->isEmpty())
            <x-portal.empty-state
                title="No Pending Vouchers"
                description="Great news! All fee challans are fully settled and paid."
                icon="wallet"
            />
        @else
            <!-- Desktop DataTable -->
            <div class="portal-table-container desktop-only">
                <div class="portal-table-scroll">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Month / Period</th>
                                <th>Challan No</th>
                                <th>Total</th>
                                <th>Balance Due</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($unpaidChallans as $c)
                            <tr>
                                <td><strong>{{ $c['month'] }}</strong></td>
                                <td><span class="portal-mono-text">{{ $c['challan_no'] }}</span></td>
                                <td><strong>Rs. {{ number_format($c['total'] + $c['late_fine'] + $c['arrears']) }}</strong></td>
                                <td><span style="color: var(--portal-danger); font-weight: 700;">Rs. {{ number_format($c['balance']) }}</span></td>
                                <td>
                                    <span style="color: {{ $c['status'] === 'overdue' ? 'var(--portal-danger)' : 'inherit' }}; font-weight: {{ $c['status'] === 'overdue' ? '700' : 'normal' }};">
                                        {{ \Carbon\Carbon::parse($c['due_date'])->format('d M Y') }}
                                    </span>
                                </td>
                                <td>
                                    @if($c['status'] === 'overdue')
                                        <x-portal.status-badge variant="danger">Overdue</x-portal.status-badge>
                                    @else
                                        <x-portal.status-badge variant="warning">Unpaid</x-portal.status-badge>
                                    @endif
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <button type="button" class="portal-btn-sm portal-btn-outline" onclick="openChallanModal({{ $c['id'] }})">Details</button>
                                        <a href="{{ route('student.fees.challans.pdf', $c['id']) }}" target="_blank" class="portal-btn-sm portal-btn-primary">
                                            <x-icon name="download" size="14" />
                                            <span>PDF</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Stacked Cards -->
            <div class="portal-mobile-challans mobile-only">
                @foreach($unpaidChallans as $c)
                    <div class="portal-mobile-challan-card" onclick="openChallanModal({{ $c['id'] }})">
                        <div class="portal-mobile-card-top">
                            <div>
                                <h4 class="portal-mobile-challan-title">{{ $c['month'] }}</h4>
                                <span class="portal-mono-text">{{ $c['challan_no'] }}</span>
                            </div>
                            <div>
                                @if($c['status'] === 'overdue')
                                    <x-portal.status-badge variant="danger">Overdue</x-portal.status-badge>
                                @else
                                    <x-portal.status-badge variant="warning">Unpaid</x-portal.status-badge>
                                @endif
                            </div>
                        </div>
                        <div class="portal-mobile-card-grid">
                            <div>
                                <span class="mobile-grid-label">Balance Due</span>
                                <span class="mobile-grid-val danger">Rs. {{ number_format($c['balance']) }}</span>
                            </div>
                            <div>
                                <span class="mobile-grid-label">Due Date</span>
                                <span class="mobile-grid-val">{{ \Carbon\Carbon::parse($c['due_date'])->format('d M Y') }}</span>
                            </div>
                        </div>
                        <div class="portal-mobile-card-actions" onclick="event.stopPropagation();">
                            <button type="button" class="portal-btn-sm portal-btn-outline" onclick="openChallanModal({{ $c['id'] }})">Details</button>
                            <a href="{{ route('student.fees.challans.pdf', $c['id']) }}" target="_blank" class="portal-btn-sm portal-btn-primary">
                                <x-icon name="download" size="14" />
                                <span>PDF</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- TAB 3: PAID CHALLANS -->
    <div id="tab-pane-paid" class="portal-tab-pane">
        @if($paidChallans->isEmpty())
            <x-portal.empty-state
                title="No Paid Vouchers"
                description="No completed fee payments recorded for this session yet."
                icon="wallet"
            />
        @else
            <!-- Desktop DataTable -->
            <div class="portal-table-container desktop-only">
                <div class="portal-table-scroll">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Month / Period</th>
                                <th>Challan No</th>
                                <th>Amount Paid</th>
                                <th>Settled Date</th>
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paidChallans as $c)
                            <tr>
                                <td><strong>{{ $c['month'] }}</strong></td>
                                <td><span class="portal-mono-text">{{ $c['challan_no'] }}</span></td>
                                <td><strong style="color: var(--portal-success);">Rs. {{ number_format($c['paid_amount']) }}</strong></td>
                                <td>{{ $c['paid_date'] ? \Carbon\Carbon::parse($c['paid_date'])->format('d M Y') : 'Settled' }}</td>
                                <td><x-portal.status-badge variant="success">Paid</x-portal.status-badge></td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <button type="button" class="portal-btn-sm portal-btn-outline" onclick="openChallanModal({{ $c['id'] }})">Details</button>
                                        <a href="{{ route('student.fees.challans.pdf', $c['id']) }}" target="_blank" class="portal-btn-sm portal-btn-secondary">
                                            <x-icon name="download" size="14" />
                                            <span>PDF</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Stacked Cards -->
            <div class="portal-mobile-challans mobile-only">
                @foreach($paidChallans as $c)
                    <div class="portal-mobile-challan-card" onclick="openChallanModal({{ $c['id'] }})">
                        <div class="portal-mobile-card-top">
                            <div>
                                <h4 class="portal-mobile-challan-title">{{ $c['month'] }}</h4>
                                <span class="portal-mono-text">{{ $c['challan_no'] }}</span>
                            </div>
                            <div>
                                <x-portal.status-badge variant="success">Paid</x-portal.status-badge>
                            </div>
                        </div>
                        <div class="portal-mobile-card-grid">
                            <div>
                                <span class="mobile-grid-label">Amount Paid</span>
                                <span class="mobile-grid-val success">Rs. {{ number_format($c['paid_amount']) }}</span>
                            </div>
                            <div>
                                <span class="mobile-grid-label">Settled On</span>
                                <span class="mobile-grid-val">{{ $c['paid_date'] ? \Carbon\Carbon::parse($c['paid_date'])->format('d M Y') : 'Settled' }}</span>
                            </div>
                        </div>
                        <div class="portal-mobile-card-actions" onclick="event.stopPropagation();">
                            <button type="button" class="portal-btn-sm portal-btn-outline" onclick="openChallanModal({{ $c['id'] }})">Details</button>
                            <a href="{{ route('student.fees.challans.pdf', $c['id']) }}" target="_blank" class="portal-btn-sm portal-btn-secondary">
                                <x-icon name="download" size="14" />
                                <span>PDF</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<!-- Payment History Section -->
<div class="portal-payment-history-section">
    <div class="portal-section-header">
        <div>
            <h2 class="portal-section-title">Payment History &amp; Receipts</h2>
            <p class="portal-section-sub">Verified payment receipts with downloadable official PDF proofs</p>
        </div>
    </div>

    @if($payments->isEmpty())
        <x-portal.empty-state
            title="No Payment History Recorded"
            description="Payments made at the school accounts desk or designated bank branch will appear here once cleared."
            icon="wallet"
        />
    @else
        <!-- Desktop Receipts Table -->
        <div class="portal-table-container desktop-only">
            <div class="portal-table-scroll">
                <table class="portal-table">
                    <thead>
                        <tr>
                            <th>Receipt No</th>
                            <th>Date</th>
                            <th>Challan No</th>
                            <th>Payment Method</th>
                            <th>Amount Paid</th>
                            <th>Received By</th>
                            <th style="text-align: right;">Official Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $p)
                        <tr>
                            <td>
                                <strong class="portal-mono-text" style="color: var(--portal-primary);">{{ $p['receipt_no'] }}</strong>
                            </td>
                            <td>{{ \Carbon\Carbon::parse($p['payment_date'])->format('d M Y') }}</td>
                            <td><span class="portal-mono-text">{{ $p['challan_no'] }}</span></td>
                            <td>
                                <span class="portal-badge info">{{ $p['method'] }}</span>
                            </td>
                            <td>
                                <strong style="color: var(--portal-success);">Rs. {{ number_format($p['amount']) }}</strong>
                            </td>
                            <td>{{ $p['received_by'] }}</td>
                            <td style="text-align: right;">
                                <a
                                    href="{{ route('student.fees.payments.receipt', $p['id']) }}"
                                    target="_blank"
                                    class="portal-btn-sm portal-btn-primary"
                                    title="Download Official PDF Receipt"
                                >
                                    <x-icon name="download" size="14" />
                                    <span>Download Receipt</span>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile Receipts List -->
        <div class="portal-mobile-receipts mobile-only">
            @foreach($payments as $p)
                <div class="portal-mobile-receipt-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div>
                            <span class="portal-mono-text" style="font-weight: 700; color: var(--portal-primary);">{{ $p['receipt_no'] }}</span>
                            <div style="font-size: 12px; color: var(--portal-muted);">{{ \Carbon\Carbon::parse($p['payment_date'])->format('d M Y') }}</div>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 15px; font-weight: 800; color: var(--portal-success);">Rs. {{ number_format($p['amount']) }}</span>
                            <div style="font-size: 11px; color: var(--portal-muted);">{{ $p['method'] }}</div>
                        </div>
                    </div>
                    <div style="font-size: 12px; color: var(--portal-muted); margin-bottom: 12px;">
                        Challan: <strong>{{ $p['challan_no'] }}</strong> &middot; Received by: <strong>{{ $p['received_by'] }}</strong>
                    </div>
                    <a href="{{ route('student.fees.payments.receipt', $p['id']) }}" target="_blank" class="portal-btn-sm portal-btn-primary" style="width: 100%; justify-content: center;">
                        <x-icon name="download" size="14" />
                        <span>Download Official Receipt (PDF)</span>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</div>

<!-- Official Information & Bank Deposit Policy -->
<div class="portal-card portal-info-notice-box">
    <div style="display: flex; align-items: flex-start; gap: 14px;">
        <div class="portal-notice-icon-wrap">
            <x-icon name="info" size="22" />
        </div>
        <div style="flex: 1;">
            <h4 style="margin: 0 0 4px 0; font-size: 15px; font-weight: 700; color: var(--portal-text);">Payment Instructions &amp; Bank Information</h4>
            <p style="margin: 0; font-size: 13.5px; color: var(--portal-muted); line-height: 1.55;">
                Pay at any authorized branch of Allied Bank Ltd (ABL) or at the School Accounts Office using the printed fee challan. Please retain the stamped Student Copy as official payment clearance. For fee adjustment requests, sibling concessions, or financial inquiries, please visit the Accounts Department during working hours.
            </p>
        </div>
    </div>
</div>

<!-- Challan Detail Modal -->
<x-portal.modal id="challan-detail-modal" title="Fee Challan Details">
    <div id="challan-modal-loader" style="padding: 24px 0; text-align: center; display: none;">
        <x-portal.skeleton-loader type="table" :count="1" />
    </div>

    <div id="challan-modal-content">
        <!-- Injected via JavaScript upon clicking a challan -->
    </div>

    <x-slot:footer>
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; gap: 12px;">
            <button type="button" class="portal-btn portal-btn-secondary" data-portal-modal-close>
                Close
            </button>
            <a href="#" id="modal-download-pdf-btn" target="_blank" class="portal-btn portal-btn-primary">
                <x-icon name="download" size="16" />
                <span>Download Challan (PDF)</span>
            </a>
        </div>
    </x-slot:footer>
</x-portal.modal>

<style>
/* Fees Page Specific Layout & Styles */
.portal-fee-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}

.portal-select-input {
    background: #ffffff;
    border: 1px solid var(--portal-border);
    border-radius: var(--portal-radius-sm);
    padding: 7px 12px;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--portal-text);
    outline: none;
    cursor: pointer;
    transition: border-color 0.15s ease;
}
.portal-select-input:focus {
    border-color: var(--portal-primary);
}

.portal-fee-highlight-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 28px;
}
@media (max-width: 900px) {
    .portal-fee-highlight-row {
        grid-template-columns: 1fr;
    }
}

.portal-highlight-challan-card {
    background: #ffffff;
    border: 1px solid var(--portal-border);
    border-radius: var(--portal-radius);
    padding: 24px;
    box-shadow: var(--portal-shadow);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.portal-card-tag {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--portal-primary);
    display: inline-block;
    margin-bottom: 4px;
}
.portal-highlight-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 18px;
}
.portal-highlight-title {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: var(--portal-text);
}
.portal-highlight-sub {
    margin: 4px 0 0;
    font-size: 13px;
    color: var(--portal-muted);
}
.portal-amount-box {
    margin-bottom: 16px;
}
.portal-amount-label {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    color: var(--portal-muted);
    font-weight: 700;
    letter-spacing: 0.5px;
}
.portal-amount-value {
    font-size: 28px;
    font-weight: 800;
    color: var(--portal-text);
}
.portal-amount-value.danger {
    color: var(--portal-danger);
}
.portal-highlight-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

/* Cleared Card State */
.portal-cleared-highlight-content {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 0;
}
.portal-cleared-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: var(--portal-success-light);
    color: var(--portal-success);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Donut Chart Card */
.portal-chart-card {
    background: #ffffff;
    border: 1px solid var(--portal-border);
    border-radius: var(--portal-radius);
    padding: 22px;
    box-shadow: var(--portal-shadow);
}
.portal-chart-title {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: var(--portal-text);
}
.portal-chart-sub {
    margin: 2px 0 16px;
    font-size: 12px;
    color: var(--portal-muted);
}
.portal-chart-visual-wrap {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 16px;
}
.portal-donut-wrap {
    position: relative;
    width: 90px;
    height: 90px;
    flex-shrink: 0;
}
.portal-donut-svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}
.donut-bg {
    fill: none;
    stroke: #e2e8f0;
    stroke-width: 3.8;
}
.donut-segment {
    fill: none;
    stroke: #16a34a;
    stroke-width: 3.8;
    stroke-linecap: round;
    transition: stroke-dasharray 0.5s ease;
}
.portal-donut-text {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.portal-donut-number {
    font-size: 17px;
    font-weight: 800;
    color: var(--portal-text);
    line-height: 1;
}
.portal-donut-label {
    font-size: 10px;
    color: var(--portal-muted);
    font-weight: 600;
    text-transform: uppercase;
}
.portal-chart-legend {
    display: flex;
    flex-direction: column;
    gap: 12px;
    flex: 1;
}
.portal-legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
}
.portal-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
.portal-legend-dot.success { background: #16a34a; }
.portal-legend-dot.warning { background: #f59e0b; }
.portal-legend-dot.muted { background: #94a3b8; }
.portal-legend-title {
    font-size: 11px;
    color: var(--portal-muted);
}
.portal-legend-value {
    font-size: 14px;
    font-weight: 700;
    color: var(--portal-text);
}
.portal-progress-bar-wrap {
    background: #f1f5f9;
    border-radius: 999px;
    height: 8px;
    overflow: hidden;
}
.portal-progress-bar {
    background: #16a34a;
    height: 100%;
    border-radius: 999px;
    transition: width 0.4s ease;
}

/* Section Headers & Tabs */
.portal-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 16px;
}
.portal-section-title {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
    color: var(--portal-text);
}
.portal-section-sub {
    margin: 2px 0 0;
    font-size: 13px;
    color: var(--portal-muted);
}
.portal-tabs {
    display: flex;
    background: #e2e8f0;
    padding: 4px;
    border-radius: 10px;
    gap: 4px;
}
.portal-tab-btn {
    border: none;
    background: transparent;
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 600;
    color: var(--portal-muted);
    border-radius: 7px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.portal-tab-btn.active {
    background: #ffffff;
    color: var(--portal-text);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.portal-tab-pane {
    display: none;
}
.portal-tab-pane.active {
    display: block;
}

/* Mobile Stacked Challan Cards */
.portal-mobile-challans, .portal-mobile-receipts {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.portal-mobile-challan-card, .portal-mobile-receipt-card {
    background: #ffffff;
    border: 1px solid var(--portal-border);
    border-radius: var(--portal-radius);
    padding: 16px;
    box-shadow: var(--portal-shadow-sm);
}
.portal-mobile-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}
.portal-mobile-challan-title {
    margin: 0 0 2px 0;
    font-size: 15px;
    font-weight: 700;
    color: var(--portal-text);
}
.portal-mobile-card-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    padding: 10px 0;
    border-top: 1px solid var(--portal-border-subtle);
    border-bottom: 1px solid var(--portal-border-subtle);
    margin-bottom: 12px;
}
.mobile-grid-label {
    display: block;
    font-size: 11px;
    color: var(--portal-muted);
    margin-bottom: 2px;
}
.mobile-grid-val {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--portal-text);
}
.mobile-grid-val.danger { color: var(--portal-danger); }
.mobile-grid-val.success { color: var(--portal-success); }
.portal-mobile-card-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

/* Info Notice Box */
.portal-info-notice-box {
    margin-top: 28px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: var(--portal-radius);
    padding: 18px 22px;
}
.portal-notice-icon-wrap {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--portal-primary-light);
    color: var(--portal-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Modal details table styling */
.modal-breakdown-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin-bottom: 16px;
}
.modal-breakdown-table th {
    background: #f8fafc;
    padding: 8px 10px;
    text-align: left;
    border-bottom: 1px solid var(--portal-border);
    color: var(--portal-muted);
    font-size: 12px;
    text-transform: uppercase;
}
.modal-breakdown-table td {
    padding: 9px 10px;
    border-bottom: 1px solid var(--portal-border-subtle);
}
.modal-totals-grid {
    background: #f8fafc;
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 14px;
    font-size: 13px;
}
.modal-totals-row {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
}
.modal-totals-row.grand {
    border-top: 1.5px solid var(--portal-border);
    margin-top: 6px;
    padding-top: 8px;
    font-weight: 800;
    font-size: 15px;
}

.portal-mono-text {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12.5px;
}

.desktop-only { display: block; }
.mobile-only { display: none; }
@media (max-width: 768px) {
    .desktop-only { display: none !important; }
    .mobile-only { display: flex !important; }
}
</style>

<script>
// Tab Switching
function switchChallanTab(tabName) {
    document.querySelectorAll('.portal-tab-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.setAttribute('aria-selected', 'false');
    });
    document.querySelectorAll('.portal-tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });

    const activeBtn = document.getElementById(`tab-btn-${tabName}`);
    const activePane = document.getElementById(`tab-pane-${tabName}`);
    if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.setAttribute('aria-selected', 'true');
    }
    if (activePane) {
        activePane.classList.add('active');
    }
}

// Print Challan helper (opens in new tab and triggers print dialog)
function printChallan(pdfUrl) {
    const printWindow = window.open(pdfUrl, '_blank');
    if (printWindow) {
        printWindow.focus();
    } else {
        showPortalToast('Popup blocker prevented opening the PDF. Please allow popups or use Download PDF.', 'danger');
    }
}

// Global Toast helper
function showPortalToast(message, type = 'success') {
    const container = document.getElementById('portal-toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `portal-toast ${type}`;
    toast.setAttribute('data-auto-dismiss', 'true');
    toast.style.padding = '12px 16px';
    toast.style.borderRadius = '10px';
    toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    toast.style.background = type === 'danger' ? '#ef4444' : (type === 'warning' ? '#f59e0b' : '#10b981');
    toast.style.color = '#ffffff';
    toast.style.fontSize = '13.5px';
    toast.style.fontWeight = '600';
    toast.style.display = 'flex';
    toast.style.alignItems = 'center';
    toast.style.justifyContent = 'space-between';
    toast.style.gap = '12px';
    toast.innerHTML = `<span>${message}</span><button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:#fff;cursor:pointer;font-size:16px;">&times;</button>`;

    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4500);
}

// Open Challan Details Modal via AJAX
async function openChallanModal(challanId) {
    const modal = document.getElementById('challan-detail-modal');
    const loader = document.getElementById('challan-modal-loader');
    const content = document.getElementById('challan-modal-content');
    const downloadBtn = document.getElementById('modal-download-pdf-btn');

    if (!modal) return;

    modal.classList.add('open');
    loader.style.display = 'block';
    content.innerHTML = '';
    downloadBtn.href = `/student/fees/challans/${challanId}/pdf`;

    try {
        const response = await fetch(`/student/fees/challans/${challanId}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error('Failed to load challan details.');
        }

        const data = await response.json();
        loader.style.display = 'none';

        // Render detailed fee breakdown
        let feeHeadsHtml = '';
        if (data.fee_heads && data.fee_heads.length > 0) {
            data.fee_heads.forEach(item => {
                feeHeadsHtml += `
                    <tr>
                        <td><strong>${item.name}</strong>${item.override_reason ? `<div style="font-size:11px;color:#64748b;">${item.override_reason}</div>` : ''}</td>
                        <td style="text-align:right;">Rs. ${Number(item.amount).toLocaleString()}</td>
                        <td style="text-align:right;color:#16a34a;">${item.discount > 0 ? `- Rs. ${Number(item.discount).toLocaleString()}` : '—'}</td>
                        <td style="text-align:right;font-weight:700;">Rs. ${Number(item.net).toLocaleString()}</td>
                    </tr>
                `;
            });
        }

        let paymentsHtml = '';
        if (data.payments && data.payments.length > 0) {
            paymentsHtml = `
                <div style="margin-top: 14px;">
                    <h5 style="margin: 0 0 6px 0; font-size: 13px; font-weight: 700; color: var(--portal-text);">Payments Received</h5>
                    <table class="modal-breakdown-table" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Date</th>
                                <th>Method</th>
                                <th style="text-align:right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.payments.map(p => `
                                <tr>
                                    <td><span class="portal-mono-text">${p.receipt_no}</span></td>
                                    <td>${p.payment_date}</td>
                                    <td>${p.method}</td>
                                    <td style="text-align:right;font-weight:700;color:#16a34a;">Rs. ${Number(p.amount).toLocaleString()}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        const grandTotal = Number(data.total) + Number(data.late_fine) + Number(data.arrears);

        content.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid var(--portal-border-subtle);">
                <div>
                    <h4 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--portal-text);">${data.month} Challan</h4>
                    <span class="portal-mono-text" style="color: var(--portal-muted); font-size: 12px;">Challan # ${data.challan_no}</span>
                </div>
                <div>
                    <span class="portal-badge ${data.status === 'paid' ? 'success' : (data.status === 'overdue' ? 'danger' : 'warning')}">
                        ${data.status.toUpperCase()}
                    </span>
                </div>
            </div>

            <table class="modal-breakdown-table">
                <thead>
                    <tr>
                        <th>Particulars</th>
                        <th style="text-align:right;">Amount</th>
                        <th style="text-align:right;">Discount</th>
                        <th style="text-align:right;">Net Amount</th>
                    </tr>
                </thead>
                <tbody>
                    ${feeHeadsHtml}
                </tbody>
            </table>

            <div class="modal-totals-grid">
                <div class="modal-totals-row">
                    <span style="color: var(--portal-muted);">Fee Subtotal:</span>
                    <span>Rs. ${Number(data.subtotal).toLocaleString()}</span>
                </div>
                ${data.discount > 0 ? `
                    <div class="modal-totals-row" style="color: #16a34a;">
                        <span>Concessions / Discount:</span>
                        <span>- Rs. ${Number(data.discount).toLocaleString()}</span>
                    </div>
                ` : ''}
                ${data.arrears > 0 ? `
                    <div class="modal-totals-row" style="color: #dc2626;">
                        <span>Previous Arrears:</span>
                        <span>+ Rs. ${Number(data.arrears).toLocaleString()}</span>
                    </div>
                ` : ''}
                ${data.late_fine > 0 ? `
                    <div class="modal-totals-row" style="color: #dc2626;">
                        <span>Late Fee Charge:</span>
                        <span>+ Rs. ${Number(data.late_fine).toLocaleString()}</span>
                    </div>
                ` : ''}
                <div class="modal-totals-row grand">
                    <span>Total Payable:</span>
                    <span>Rs. ${grandTotal.toLocaleString()}</span>
                </div>
                <div class="modal-totals-row" style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed #cbd5e1;">
                    <span style="color: #16a34a; font-weight: 600;">Total Paid:</span>
                    <span style="color: #16a34a; font-weight: 700;">Rs. ${Number(data.paid_amount).toLocaleString()}</span>
                </div>
                <div class="modal-totals-row">
                    <span style="color: ${data.balance > 0 ? '#dc2626' : '#16a34a'}; font-weight: 700;">Outstanding Balance:</span>
                    <span style="color: ${data.balance > 0 ? '#dc2626' : '#16a34a'}; font-weight: 800;">Rs. ${Number(data.balance).toLocaleString()}</span>
                </div>
            </div>

            <div style="font-size: 12px; color: var(--portal-muted); margin-bottom: 8px;">
                Due Date: <strong>${data.due_date}</strong> &middot; Class: <strong>${data.class_name}</strong>
            </div>

            ${paymentsHtml}
        `;

    } catch (err) {
        loader.style.display = 'none';
        content.innerHTML = `
            <div style="text-align: center; padding: 24px; color: var(--portal-danger);">
                <p>Failed to load challan details. Please check your connection or try again.</p>
                <button type="button" class="portal-btn-sm portal-btn-outline" onclick="openChallanModal(${challanId})">
                    Retry
                </button>
            </div>
        `;
        showPortalToast('Failed to load challan details.', 'danger');
    }
}
</script>
@endsection

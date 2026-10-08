@extends('layouts.admin')
@section('title', 'Fee Reports')
@section('content')
<div class="fee-report-page">
@include('fees.billing.header', ['title' => 'Reports'])
<form method="GET" class="panel student-form"><div class="student-form-grid"><div class="admission-field"><label for="report-from">From</label><input id="report-from" type="date" name="from" value="{{ $from }}" required></div><div class="admission-field"><label for="report-to">To</label><input id="report-to" type="date" name="to" value="{{ $to }}" required></div><div class="admission-field"><label for="report-class">Class</label><select id="report-class" name="class_id"><option value="">All classes</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>@endforeach</select></div></div><div class="form-actions"><button class="button-primary">View Report</button><a class="button-secondary csv-export-button" href="{{ request()->fullUrlWithQuery(['from' => $from, 'to' => $to, 'export' => 'csv']) }}">Export CSV</a></div></form>
<div class="fee-report-summary">
@foreach([['Collected in selected dates', $collected, 'green', 'wallet', 'Received payments, excluding reversals'], ["Today's collection in selected dates", $todayCollected, 'blue', 'calendar', 'Payments dated today within your filters'], ['Total outstanding as of today', $outstanding, 'orange', 'clock', 'Remaining balance across active vouchers']] as [$label, $amount, $tone, $icon, $note])
<div class="panel fee-report-stat fee-report-stat-{{ $tone }}"><div class="fee-report-stat-heading"><span>{{ $label }}</span><span class="fee-report-stat-icon"><x-icon :name="$icon" size="20"/></span></div><strong><small>PKR</small> {{ number_format($amount / 100, 2) }}</strong><p>{{ $note }}</p></div>
@endforeach
</div>
<section class="panel fee-report-section">
@include('fees.billing.student-balances')
</section>
<section class="panel fee-report-section">
<div class="panel-heading"><div><h2>Class-wise Summary</h2><p>Collection and remaining balances by class</p></div><span class="badge badge-neutral">{{ $classTotals->count() }} classes</span></div>
<div class="student-table-scroll"><table class="student-table fee-report-table"><thead><tr><th>Class</th><th class="fee-report-money">Collected in selected dates (PKR)</th><th class="fee-report-money">Outstanding as of today (PKR)</th></tr></thead><tbody>@forelse($classTotals as $row)<tr><td><strong>{{ $row['class'] }}</strong></td><td class="fee-report-money fee-report-collected">{{ number_format($row['collected'] / 100, 2) }}</td><td class="fee-report-money">{{ number_format($row['outstanding'] / 100, 2) }}</td></tr>@empty<tr><td colspan="3"><div class="fee-report-empty">No vouchers issued yet. Generate vouchers to see class balances here.</div></td></tr>@endforelse</tbody></table></div>
<p class="fee-report-note">Reversed receipts and cancelled vouchers are excluded from collection and outstanding totals.</p>
<div class="fee-report-methods"><h3>Payment methods</h3><div class="fee-report-method-grid">@forelse($methodTotals as $method => $amount)<div class="fee-report-method"><span><x-icon name="wallet" size="16"/>{{ $method }}</span><strong>PKR {{ number_format($amount / 100, 2) }}</strong></div>@empty<p class="fee-report-empty">No payments in this date range.</p>@endforelse</div></div>
</section>
<section class="panel fee-report-section"><div class="panel-heading"><div><h2>Payment Ledger</h2><p>Open a receipt to view its details and payment history</p></div><span class="badge badge-neutral">{{ $payments->total() }} receipts</span></div>@include('fees.billing.payments-table')<div class="student-pagination">{{ $payments->links() }}</div></section>
</div>
@endsection

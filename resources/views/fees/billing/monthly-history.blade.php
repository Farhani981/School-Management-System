@extends('layouts.admin')
@section('title', 'Student Fee History')
@section('content')
<div class="fee-report-page">
@include('fees.billing.header',['title'=>'Student Fee History','description'=>'Month-wise payments and remaining fees for '.$student->full_name.'.'])
<div class="student-fee-history-heading"><div><h2>{{ $student->full_name }}</h2><p>{{ $student->admission_no }} &middot; {{ $student->schoolClass?->name ?? 'Unassigned class' }}</p></div><a class="button-secondary" href="{{ route('fees.reports.index') }}">Back to Reports</a></div>
<div class="student-fee-month-summary">
@foreach([
    ['Fully paid months',$monthCounts->get('Paid',0),'green'],
    ['Months with balance',$months->where('remaining','>',0)->count(),'red'],
    ['Partially paid months',$monthCounts->get('Partial',0),'amber']
] as [$label,$count,$tone])<div class="student-fee-status-card student-fee-tone-{{ $tone }}"><span>{{ $label }}</span><strong>{{ $count }}</strong><small>{{ $label === 'Months with balance' ? 'Includes partial and unpaid months' : 'Across issued vouchers' }}</small></div>@endforeach
</div>
<div class="student-fee-history-totals"><span>Total fee <strong>PKR {{ number_format($months->sum('total')/100,2) }}</strong></span><span>Paid <strong class="student-fee-paid">PKR {{ number_format($months->sum('paid')/100,2) }}</strong></span><span>Remaining <strong class="student-fee-due">PKR {{ number_format($months->sum('remaining')/100,2) }}</strong></span></div>
<section class="panel fee-report-section"><div class="panel-heading"><div><h2>Monthly Fee History</h2><p>Each billing month shows its paid amount and remaining balance.</p></div><span class="badge badge-neutral">{{ $months->count() }} billed months</span></div>
<p class="fee-report-note">Based on issued, active vouchers, grouped by billing month and academic session. Months without issued vouchers are not counted as unpaid. Balances include applicable fines as of {{ today()->format('d M Y') }}; cancelled vouchers and reversed receipts are excluded from totals.</p>
<div class="student-table-scroll"><table class="student-table student-fee-balance-table"><thead><tr><th>Billing Month</th><th>Session</th><th>Total Fee (PKR)</th><th>Paid (PKR)</th><th>Remaining (PKR)</th><th>Status</th><th>Last Payment</th><th>Vouchers</th></tr></thead><tbody>
@forelse($months as $month)<tr><td><strong>{{ $month['label'] }}</strong></td><td>{{ $month['session'] }}</td><td class="fee-report-money">{{ number_format($month['total']/100,2) }}</td><td class="fee-report-money student-fee-paid">{{ number_format($month['paid']/100,2) }}</td><td class="fee-report-money"><strong class="{{ $month['remaining'] > 0 ? 'student-fee-due' : 'student-fee-paid' }}">{{ number_format($month['remaining']/100,2) }}</strong>@if($month['overdue'] > 0)<small class="student-fee-overdue">{{ number_format($month['overdue']/100,2) }} overdue</small>@endif</td><td><span class="student-fee-badge student-fee-badge-{{ strtolower($month['status']) }}">{{ ['Paid'=>'Fully Paid','Partial'=>'Partially Paid','Unpaid'=>'Not Paid'][$month['status']] }}</span></td><td>{{ $month['last_payment']?->format('d M Y') ?? 'No payment' }}</td><td>@foreach($month['vouchers'] as $voucher)<a class="student-fee-voucher-link" href="{{ route('fees.vouchers.show',$voucher) }}">{{ $voucher->number }}</a>@endforeach</td></tr>
@empty<tr><td colspan="8"><div class="fee-report-empty">No active fee vouchers have been issued for this student.</div></td></tr>@endforelse
</tbody></table></div></section>
<section class="panel fee-report-section"><div class="panel-heading"><div><h2>Payment History</h2><p>Open a receipt to view payment details. Reversed receipts are shown for reference.</p></div></div>@include('fees.billing.payments-table')</section>
</div>
@endsection

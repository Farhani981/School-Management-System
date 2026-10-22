@extends('layouts.admin')
@section('title', 'Staff Salary')
@section('content')
<div class="accounts-salary-page">
<div class="page-heading"><div><div class="breadcrumb">Accounts / Staff Salary</div><h1>Staff Salary</h1><p>Generate salaries from staff salary structures, then record payments.</p></div><a class="button-secondary" href="{{ route('teachers.payroll.index', ['month'=>$month]) }}">Payroll Setup</a></div>
@include('accounts.nav')
@include('students.partials.feedback')
<section class="panel salary-toolbar">
    <form method="GET" class="salary-month-filter"><div class="admission-field"><label for="salary-month">Salary Month</label><input id="salary-month" type="month" name="month" value="{{ $month }}" required></div><button class="button-secondary">View Month</button></form>
    <form method="POST" action="{{ route('accounts.salaries.generate') }}">@csrf<input type="hidden" name="month" value="{{ $month }}"><button class="button-primary">Generate Salaries</button></form>
</section>
<section class="panel salary-records">
    <div class="panel-heading"><div><h2>{{ \Carbon\Carbon::createFromFormat('!Y-m', $month)->format('F Y') }} Salaries</h2><p>Amounts in PKR. Open Record Payment after paying a staff member.</p></div><span class="badge badge-neutral">{{ $payrolls->total() }} records</span></div>
    <div class="student-table-scroll"><table class="student-table salary-table"><thead><tr><th>Staff / Designation</th><th class="salary-number">Basic Salary</th><th class="salary-number">Allowance</th><th class="salary-number">Deduction</th><th class="salary-number">Net Salary</th><th>Payment</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @forelse($payrolls as $payroll)
    <tr>
        <td class="salary-staff"><strong>{{ $payroll->teacher?->name ?: 'Unavailable' }}</strong><small>{{ $payroll->teacher?->designation?->name ?: 'Designation not provided' }}</small><small>{{ $payroll->teacher?->employee_id }}</small></td>
        <td class="salary-number">{{ number_format($payroll->basic_salary, 2) }}</td><td class="salary-number">{{ number_format($payroll->allowances, 2) }}</td><td class="salary-number">{{ number_format($payroll->deductions + $payroll->absence_deduction + $payroll->advances, 2) }}</td><td class="salary-number salary-net"><strong>{{ number_format($payroll->net_salary, 2) }}</strong></td>
        <td class="salary-payment-info">@if($payroll->paid_at)<span>{{ $payroll->paid_at->format('d M Y') }}</span><small>{{ $payroll->payment_method ?: 'Method not recorded' }}</small>@else<span>Not paid yet</span>@endif</td>
        <td><span class="salary-status {{ $payroll->status === 'Paid' ? 'is-paid' : 'is-pending' }}">{{ $payroll->status }}</span></td>
        <td class="salary-actions">
            @if($payroll->status !== 'Paid')
            <details class="salary-payment-editor" @if($errors->any() && (string) old('payroll_id') === (string) $payroll->id) open @endif>
                <summary class="button-primary">Record Payment</summary>
                <form method="POST" action="{{ route('accounts.salaries.paid', $payroll) }}">@csrf<input type="hidden" name="payroll_id" value="{{ $payroll->id }}">
                    <p>Pay {{ $payroll->teacher?->name }}<strong>PKR {{ number_format($payroll->net_salary, 2) }}</strong></p>
                    <label for="payment-date-{{ $payroll->id }}">Payment Date</label><input id="payment-date-{{ $payroll->id }}" type="date" name="payment_date" value="{{ (string) old('payroll_id') === (string) $payroll->id ? old('payment_date', today()->toDateString()) : today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                    <label for="payment-method-{{ $payroll->id }}">Payment Method</label><select id="payment-method-{{ $payroll->id }}" name="payment_method" required>@foreach(['Cash','Bank Transfer','Cheque'] as $method)<option @selected((string) old('payroll_id') === (string) $payroll->id && old('payment_method') === $method)>{{ $method }}</option>@endforeach</select>
                    <button class="button-primary">Mark Paid</button>
                </form>
            </details>
            @endif
            <a class="button-secondary" href="{{ route('teachers.payroll.slip', $payroll) }}">Salary Slip / Print</a>
        </td>
    </tr>
    @empty<tr><td colspan="8" class="salary-empty">No salaries generated for this month. Use Generate Salaries to create staff records.</td></tr>@endforelse
    </tbody></table></div><div class="student-pagination">{{ $payrolls->links() }}</div>
</section>
</div>
@endsection

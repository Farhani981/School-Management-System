@extends('layouts.admin')
@section('title', 'Receipt '.$payment->number)
@section('content')
@include('fees.billing.header', ['title' => 'Payment Receipt'])
<section class="panel student-form fee-print-document">
<div class="page-heading"><div><h2>{{ $payment->number }}</h2><p>School Fee Payment Receipt</p></div><strong>{{ $payment->reversed_at ? 'REVERSED' : 'PAYMENT RECEIVED' }}</strong></div>
<dl class="student-profile-grid">@foreach(['Student' => $payment->voucher->student_name, 'Admission no' => $payment->voucher->admission_no, 'Class' => $payment->voucher->class_name, 'Voucher' => $payment->voucher->number, 'Payment date' => $payment->payment_date->format('d M Y'), 'Method' => $payment->method, 'Reference' => $payment->reference ?: '—', 'Received by' => $payment->receiver?->name, 'Amount received' => 'PKR '.number_format($payment->amount_cents / 100, 2), 'Late fine at payment' => 'PKR '.number_format($payment->fine_cents / 100, 2), 'Balance after payment' => 'PKR '.number_format($payment->balance_after_cents / 100, 2)] as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>
@if($payment->notes)<p>Notes: {{ $payment->notes }}</p>@endif
@if($payment->reversed_at)<p>Reversed by {{ $payment->reverser?->name }} on {{ $payment->reversed_at->format('d M Y H:i') }}: {{ $payment->reversal_reason }}</p>@endif
<div class="form-actions fee-no-print"><button type="button" class="button-primary print-pdf-button" onclick="window.print()">Print / Save PDF</button><a class="button-secondary" href="{{ route('fees.vouchers.show', $payment->voucher) }}">Open Voucher</a></div>
</section>
@unless($payment->reversed_at)<details class="panel student-form fee-no-print" style="margin-top:20px"><summary>Reverse Incorrect Payment</summary><form method="POST" action="{{ route('fees.payments.reverse', $payment) }}" onsubmit="return confirm('Reverse this payment and restore the voucher balance?');">@csrf<p class="photo-help">The original receipt remains in the history and collection totals will exclude this payment.</p><div class="admission-field"><label for="reversal-reason">Reason</label><textarea id="reversal-reason" name="reason" minlength="3" maxlength="1000" required></textarea></div><div class="form-actions"><button class="button-danger">Reverse Payment</button></div></form></details>@endunless
@endsection

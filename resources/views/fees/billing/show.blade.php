@extends('layouts.admin')
@section('title', $voucher->number)
@section('content')
<div class="fee-no-print">
    @include('fees.billing.header', ['title' => 'Voucher '.$voucher->number])
</div>

<article class="fee-voucher-document">
    <header class="fee-voucher-heading">
        <div>
            <p class="fee-voucher-eyebrow">Fee voucher</p>
            <h1>{{ $voucher->number }}</h1>
        </div>
        <dl>
            <div><dt>Issue date</dt><dd>{{ $voucher->created_at->format('d M Y') }}</dd></div>
            <div><dt>Due date</dt><dd>{{ $voucher->due_date->format('d M Y') }}</dd></div>
        </dl>
    </header>

    <section class="fee-voucher-section">
        <h2>Student information</h2>
        <dl class="fee-voucher-student-grid">
            <div><dt>Name</dt><dd>{{ $voucher->student_name }}</dd></div>
            <div><dt>Admission no.</dt><dd>{{ $voucher->admission_no }}</dd></div>
            <div><dt>Class</dt><dd>{{ $voucher->class_name }}</dd></div>
            <div><dt>Billing month</dt><dd>{{ sprintf('%04d-%02d', $voucher->year, $voucher->month) }}</dd></div>
        </dl>
    </section>

    <section class="fee-voucher-section">
        <h2>Fee details</h2>
        <div class="student-table-scroll">
            <table class="student-table fee-voucher-items">
                <thead><tr><th>Fee</th><th>Amount (PKR)</th><th>Discount (PKR)</th><th>Net (PKR)</th></tr></thead>
                <tbody>
                    @foreach($voucher->items as $item)
                        <tr>
                            <td>{{ $item->name }}@if($item->override_reason)<small class="fee-voucher-item-note">{{ $item->override_reason }}</small>@endif</td>
                            <td>{{ number_format($item->amount_cents / 100, 2) }}</td>
                            <td>{{ number_format($item->discount_cents / 100, 2) }}</td>
                            <td>{{ number_format($item->net_cents / 100, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <dl class="fee-voucher-totals">
        <div><dt>Fee total</dt><dd>PKR {{ number_format($voucher->total_cents / 100, 2) }}</dd></div>
        <div><dt>Paid</dt><dd>PKR {{ number_format($paid / 100, 2) }}</dd></div>
        <div><dt>Late fine</dt><dd>PKR {{ number_format($fine / 100, 2) }}</dd></div>
        <div><dt>Outstanding</dt><dd>PKR {{ number_format($balance / 100, 2) }}</dd></div>
    </dl>

    <section class="fee-voucher-payment-status {{ $voucher->cancelled_at ? 'is-cancelled' : ($balance === 0 ? 'is-paid' : 'is-due') }}">
        <div><span>Payment status</span><strong>{{ $voucher->display_status }}</strong></div>
        @if($voucher->settled_on)<div><span>Paid on</span><strong>{{ $voucher->settled_on->format('d M Y') }}</strong></div>@endif
    </section>

    @if($otherBalance)<p class="fee-voucher-note">Other unpaid vouchers: PKR {{ number_format($otherBalance / 100, 2) }}. This amount is separate from this voucher.</p>@endif
    @if($voucher->cancelled_at)<p class="fee-voucher-note">Cancellation reason: {{ $voucher->cancellation_reason }}</p>@endif

    <section class="fee-voucher-section fee-voucher-history">
        <h2>Payment history</h2>
        @include('fees.billing.payments-table', ['payments' => $voucher->payments])
    </section>
    <footer class="fee-voucher-footer">Generated {{ now()->format('d M Y') }} · Voucher {{ $voucher->number }}</footer>
</article>

<div class="form-actions fee-no-print fee-voucher-actions">
    <button type="button" class="button-primary print-pdf-button" onclick="window.print()">Print / Save PDF</button>
</div>

@if(!$voucher->cancelled_at && $balance > 0)
    <form method="POST" action="{{ route('fees.payments.store', $voucher) }}" class="panel student-form fee-no-print" style="margin-top:20px">@csrf
        <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}">
        <h2>Receive Payment</h2>
        <div class="student-form-grid">
            <div class="admission-field"><label for="payment-amount">Amount (PKR)</label><input id="payment-amount" name="amount" type="number" step="0.01" min="0.01" max="{{ $balance / 100 }}" value="{{ old('amount', number_format($balance / 100, 2, '.', '')) }}" required></div>
            <div class="admission-field"><label for="payment-date">Payment date</label><input id="payment-date" name="payment_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('payment_date', today()->toDateString()) }}" required></div>
            <div class="admission-field"><label for="payment-method">Method</label><select id="payment-method" name="method">@foreach($methods as $method)<option @selected(old('method') === $method)>{{ $method }}</option>@endforeach</select></div>
            <div class="admission-field"><label for="payment-reference">Reference (optional)</label><input id="payment-reference" name="reference" maxlength="150" value="{{ old('reference') }}"></div>
            <div class="admission-field student-address"><label for="payment-notes">Notes (optional)</label><textarea id="payment-notes" name="notes" maxlength="1000">{{ old('notes') }}</textarea></div>
        </div>
        <p class="photo-help">Partial payments are supported. For a past payment date, the late fine is calculated up to that date.</p>
        <div class="form-actions"><button class="button-primary">Record Payment &amp; Create Receipt</button></div>
    </form>
@endif

@if(!$voucher->cancelled_at && !$voucher->payments->whereNull('reversed_at')->count())
    <details class="panel student-form fee-no-print" style="margin-top:20px"><summary>Cancel Voucher</summary>
        <form method="POST" action="{{ route('fees.vouchers.cancel', $voucher) }}" onsubmit="return confirm('Cancel this voucher? Its history will be retained.');">@csrf
            <div class="admission-field"><label for="cancel-reason">Cancellation reason</label><textarea id="cancel-reason" name="reason" minlength="3" maxlength="1000" required></textarea></div>
            <div class="form-actions"><button class="button-danger">Cancel Voucher</button></div>
        </form>
    </details>
@endif
@endsection

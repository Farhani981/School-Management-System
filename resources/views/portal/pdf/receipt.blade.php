<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Receipt - {{ $payment->number }}</title>
    <style>
        @page {
            margin: 20mm 15mm 20mm 15mm;
            size: a4 portrait;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #111827;
            margin: 0;
            padding: 0;
            line-height: 1.4;
            background: #ffffff;
        }
        .receipt-card {
            border: 2px solid #1e3a8a;
            border-radius: 6px;
            padding: 24px;
            position: relative;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .school-name {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 4px 0;
        }
        .school-sub {
            font-size: 9px;
            color: #4b5563;
            margin: 0;
        }
        .receipt-badge {
            background: #1e3a8a;
            color: #ffffff;
            font-size: 11px;
            font-weight: bold;
            padding: 6px 14px;
            border-radius: 4px;
            text-transform: uppercase;
            display: inline-block;
        }
        .meta-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 12px 14px;
            margin-bottom: 18px;
        }
        .grid-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }
        .grid-table td {
            padding: 4px 6px;
            vertical-align: top;
        }
        .label {
            color: #64748b;
            width: 25%;
        }
        .val {
            font-weight: bold;
            color: #0f172a;
            width: 25%;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin: 18px 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 10px;
        }
        .payment-table th {
            background: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 8px 10px;
            text-transform: uppercase;
            font-size: 9px;
        }
        .payment-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .text-right {
            text-align: right;
        }
        .highlight-row {
            background: #f0fdf4;
            font-weight: bold;
            font-size: 11px;
            border-top: 2px solid #16a34a;
            border-bottom: 2px solid #16a34a;
        }
        .highlight-row td {
            color: #15803d;
            padding: 10px;
        }
        .summary-box {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 24px;
        }
        .summary-box td {
            vertical-align: top;
        }
        .qr-box {
            width: 110px;
            text-align: center;
        }
        .qr-box img {
            width: 84px;
            height: 84px;
        }
        .signatures-area {
            width: 100%;
            border-collapse: collapse;
            margin-top: 40px;
        }
        .signatures-area td {
            width: 50%;
            text-align: center;
            font-size: 9px;
            color: #4b5563;
        }
        .sign-line {
            border-top: 1px dashed #64748b;
            margin: 0 40px 6px 40px;
        }
        .footer-note {
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
            margin-top: 28px;
            border-top: 1px solid #f1f5f9;
            padding-top: 8px;
        }
    </style>
</head>
<body>

<div class="receipt-card">
    <!-- Header -->
    <table class="header-table">
        <tr>
            <td>
                <h1 class="school-name">{{ $schoolName }}</h1>
                <p class="school-sub">{{ $schoolAddress }} &middot; Ph: {{ $schoolPhone }}</p>
            </td>
            <td style="text-align: right;">
                <span class="receipt-badge">Payment Receipt</span>
            </td>
        </tr>
    </table>

    <!-- Receipt & Student Meta -->
    <div class="meta-box">
        <table class="grid-table">
            <tr>
                <td class="label">Receipt Number:</td>
                <td class="val" style="color: #1e3a8a;">{{ $payment->number }}</td>
                <td class="label">Payment Date:</td>
                <td class="val">{{ $payment->payment_date ? $payment->payment_date->format('d M Y') : 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Challan / Voucher:</td>
                <td class="val">{{ $voucher->number }}</td>
                <td class="label">Billing Period:</td>
                <td class="val">{{ sprintf('%04d-%02d', $voucher->year, $voucher->month) }}</td>
            </tr>
            <tr>
                <td class="label">Student Name:</td>
                <td class="val">{{ $voucher->student_name }}</td>
                <td class="label">Admission No:</td>
                <td class="val">{{ $voucher->admission_no }}</td>
            </tr>
            <tr>
                <td class="label">Class & Section:</td>
                <td class="val">{{ $voucher->class_name }} {{ $student->section?->name ? '('.$student->section->name.')' : '' }}</td>
                <td class="label">Roll Number:</td>
                <td class="val">{{ $student->roll_number ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Father Name:</td>
                <td class="val">{{ $student->father_name ?? '—' }}</td>
                <td class="label">Payment Status:</td>
                <td class="val" style="color: #16a34a;">PAID / CLEAR</td>
            </tr>
        </table>
    </div>

    <div class="section-title">Payment Settlement Breakdown</div>

    <!-- Payment Particulars -->
    <table class="payment-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Payment Mode</th>
                <th>Reference / Transaction ID</th>
                <th class="text-right">Amount (PKR)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Fee Voucher {{ $voucher->number }} Payment</td>
                <td><strong>{{ $payment->method }}</strong></td>
                <td>{{ $payment->reference ?: 'Cash Payment' }}</td>
                <td class="text-right">PKR {{ number_format($payment->amount_cents / 100, 2) }}</td>
            </tr>
            @if($payment->fine_cents > 0)
            <tr>
                <td>Late Fine Included in Payment</td>
                <td>—</td>
                <td>Late Clearance Charge</td>
                <td class="text-right">PKR {{ number_format($payment->fine_cents / 100, 2) }}</td>
            </tr>
            @endif
            <tr class="highlight-row">
                <td colspan="3">TOTAL AMOUNT RECEIVED</td>
                <td class="text-right">PKR {{ number_format($payment->amount_cents / 100, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3" style="color: #4b5563;">Voucher Balance Remaining After This Payment</td>
                <td class="text-right" style="font-weight: bold; color: {{ $payment->balance_after_cents > 0 ? '#b91c1c' : '#15803d' }};">
                    PKR {{ number_format($payment->balance_after_cents / 100, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Additional info & QR -->
    <table class="summary-box">
        <tr>
            <td style="padding-right: 18px;">
                <p style="margin: 0 0 6px 0; font-size: 9.5px;">
                    <strong>Received By:</strong> {{ $payment->receiver?->name ?? 'Accounts Department' }}
                </p>
                @if($payment->notes)
                <p style="margin: 0 0 6px 0; font-size: 9px; color: #4b5563;">
                    <strong>Remarks / Notes:</strong> {{ $payment->notes }}
                </p>
                @endif
                <p style="margin: 12px 0 0 0; font-size: 8.5px; color: #64748b; line-height: 1.4;">
                    * This official receipt acknowledges verified payment received in school accounts. Retain for student academic records and fee clearance during examinations.
                </p>
            </td>
            <td class="qr-box">
                <img src="{{ $qr }}" alt="Verification QR">
                <p style="margin: 4px 0 0 0; font-size: 7px; color: #64748b;">Scan to verify receipt</p>
            </td>
        </tr>
    </table>

    <!-- Signatures -->
    <table class="signatures-area">
        <tr>
            <td>
                <div class="sign-line"></div>
                <strong>Student / Parent Signature</strong>
            </td>
            <td>
                <div class="sign-line"></div>
                <strong>Authorized Cashier & Accounts Stamp</strong>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        System Generated on {{ now()->format('d M Y, h:i A') }} &middot; Receipt Reference {{ $payment->number }}
    </div>
</div>

</body>
</html>

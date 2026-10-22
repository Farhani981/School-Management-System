<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Fee Challan - {{ $voucher->number }}</title>
    <style>
        @page {
            margin: 12mm 8mm 10mm 8mm;
            size: a4 landscape;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8px;
            color: #111827;
            margin: 0;
            padding: 0;
            line-height: 1.25;
            background: #ffffff;
        }
        .challan-container {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .challan-col {
            width: 33.333%;
            vertical-align: top;
            padding: 0 6px;
        }
        .challan-col:not(:last-child) {
            border-right: 1px dashed #9ca3af;
        }
        .challan-card {
            border: 1px solid #1f2937;
            padding: 6px 8px;
            background: #ffffff;
            min-height: 480px;
        }
        .challan-header {
            text-align: center;
            border-bottom: 1.5px solid #1f2937;
            padding-bottom: 5px;
            margin-bottom: 5px;
        }
        .school-title {
            font-size: 11px;
            font-weight: bold;
            color: #111827;
            margin: 0 0 1px 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .school-sub {
            font-size: 7px;
            color: #4b5563;
            margin: 0 0 3px 0;
        }
        .copy-badge {
            display: inline-block;
            background: #1e3a8a;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            font-size: 7.5px;
        }
        .meta-grid td {
            padding: 1.5px 0;
            vertical-align: top;
        }
        .meta-label {
            color: #4b5563;
            width: 32%;
            font-weight: normal;
        }
        .meta-val {
            font-weight: bold;
            color: #111827;
            width: 68%;
        }
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            font-size: 7px;
        }
        .table-items th {
            background: #f3f4f6;
            color: #1f2937;
            font-weight: bold;
            text-align: left;
            padding: 3px 4px;
            border: 1px solid #d1d5db;
            text-transform: uppercase;
        }
        .table-items td {
            padding: 2.5px 4px;
            border: 1px solid #e5e7eb;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            font-size: 7.5px;
        }
        .totals-table td {
            padding: 2px 4px;
            border-bottom: 1px solid #f3f4f6;
        }
        .totals-table tr.grand-total td {
            border-top: 1.5px solid #1f2937;
            border-bottom: 1.5px solid #1f2937;
            font-weight: bold;
            font-size: 8.5px;
            background: #f8fafc;
        }
        .bank-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 4px;
            font-size: 6.8px;
            margin-bottom: 5px;
            border-radius: 2px;
        }
        .bank-info strong {
            color: #1e3a8a;
        }
        .bank-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .bank-grid td {
            padding: 1px 0;
        }
        .qr-section {
            width: 100%;
            margin-top: 4px;
            margin-bottom: 4px;
        }
        .qr-table {
            width: 100%;
            border-collapse: collapse;
        }
        .qr-table td {
            vertical-align: middle;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            font-size: 6.5px;
            color: #4b5563;
        }
        .signatures-table td {
            padding-top: 12px;
            border-top: 1px dotted #9ca3af;
            text-align: center;
            width: 50%;
        }
        .late-note {
            font-size: 6.5px;
            color: #b91c1c;
            margin-top: 3px;
            margin-bottom: 3px;
            font-style: italic;
            text-align: center;
        }
    </style>
</head>
<body>

@php
    $copies = [
        'Bank Copy',
        'School Copy',
        'Student Copy'
    ];
    $totalAmount = $voucher->total_cents + $fine;
@endphp

<table class="challan-container">
    <tr>
        @foreach($copies as $copyName)
        <td class="challan-col">
            <div class="challan-card">
                <!-- Header -->
                <div class="challan-header">
                    <h1 class="school-title">{{ $schoolName }}</h1>
                    <p class="school-sub">{{ $schoolAddress }} &middot; {{ $schoolPhone }}</p>
                    <span class="copy-badge">{{ $copyName }}</span>
                </div>

                <!-- Metadata -->
                <table class="meta-grid">
                    <tr>
                        <td class="meta-label">Challan No:</td>
                        <td class="meta-val">{{ $voucher->number }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Billing Month:</td>
                        <td class="meta-val">{{ $monthLabel }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Due Date:</td>
                        <td class="meta-val" style="color: #b91c1c;">{{ $voucher->due_date ? $voucher->due_date->format('d M Y') : 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Student Name:</td>
                        <td class="meta-val">{{ $voucher->student_name }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Admission / Roll:</td>
                        <td class="meta-val">{{ $voucher->admission_no }} &middot; Roll # {{ $student->roll_number ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Class & Section:</td>
                        <td class="meta-val">{{ $voucher->class_name }} {{ $student->section?->name ? '('.$student->section->name.')' : '' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Father Name:</td>
                        <td class="meta-val">{{ $student->father_name ?? '—' }}</td>
                    </tr>
                </table>

                <!-- Items Breakdown -->
                <table class="table-items">
                    <thead>
                        <tr>
                            <th>Particulars</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">Disc.</th>
                            <th class="text-right">Net (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($voucher->items as $item)
                        <tr>
                            <td>{{ $item->name }}</td>
                            <td class="text-right">{{ number_format($item->amount_cents / 100, 0) }}</td>
                            <td class="text-right">{{ $item->discount_cents > 0 ? number_format($item->discount_cents / 100, 0) : '—' }}</td>
                            <td class="text-right">{{ number_format($item->net_cents / 100, 0) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <!-- Totals -->
                <table class="totals-table">
                    <tr>
                        <td style="color: #4b5563;">Subtotal:</td>
                        <td class="text-right">PKR {{ number_format($voucher->subtotal_cents / 100, 2) }}</td>
                    </tr>
                    @if($voucher->discount_cents > 0)
                    <tr>
                        <td style="color: #16a34a;">Concession / Discount:</td>
                        <td class="text-right" style="color: #16a34a;">- PKR {{ number_format($voucher->discount_cents / 100, 2) }}</td>
                    </tr>
                    @endif
                    @if($arrears > 0)
                    <tr>
                        <td style="color: #b91c1c;">Previous Arrears:</td>
                        <td class="text-right" style="color: #b91c1c;">PKR {{ number_format($arrears / 100, 2) }}</td>
                    </tr>
                    @endif
                    @if($fine > 0)
                    <tr>
                        <td style="color: #b91c1c;">Late Fine:</td>
                        <td class="text-right" style="color: #b91c1c;">+ PKR {{ number_format($fine / 100, 2) }}</td>
                    </tr>
                    @endif
                    <tr class="grand-total">
                        <td>TOTAL PAYABLE:</td>
                        <td class="text-right">PKR {{ number_format(($totalAmount + $arrears) / 100, 2) }}</td>
                    </tr>
                    @if($paid > 0)
                    <tr>
                        <td style="color: #16a34a; font-weight: bold;">Amount Paid:</td>
                        <td class="text-right" style="color: #16a34a; font-weight: bold;">PKR {{ number_format($paid / 100, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="color: #b91c1c; font-weight: bold;">Balance Remaining:</td>
                        <td class="text-right" style="color: #b91c1c; font-weight: bold;">PKR {{ number_format($balance / 100, 2) }}</td>
                    </tr>
                    @endif
                </table>

                <!-- Bank Instructions -->
                <div class="bank-info">
                    <table class="bank-grid">
                        <tr>
                            <td style="width: 40%; color: #4b5563;">Bank:</td>
                            <td style="width: 60%; font-weight: bold;">{{ $bankName }}</td>
                        </tr>
                        <tr>
                            <td style="color: #4b5563;">Branch:</td>
                            <td>{{ $bankBranch }}</td>
                        </tr>
                        <tr>
                            <td style="color: #4b5563;">Account Title:</td>
                            <td>{{ $accountTitle }}</td>
                        </tr>
                        <tr>
                            <td style="color: #4b5563;">Account No:</td>
                            <td style="font-weight: bold; color: #1e3a8a;">{{ $accountNo }}</td>
                        </tr>
                        <tr>
                            <td style="color: #4b5563;">IBAN:</td>
                            <td>{{ $iban }}</td>
                        </tr>
                    </table>
                </div>

                @if($settings && $settings->fine_type !== 'none' && $settings->fine_amount > 0)
                <p class="late-note">
                    * Note: Late fee of Rs. {{ number_format($settings->fine_amount, 0) }}{{ $settings->fine_type === 'per_day' ? '/day' : ' flat' }} applies after due date (+{{ $settings->grace_days ?? 0 }} grace days).
                </p>
                @endif

                <!-- QR Code & Instructions -->
                <div class="qr-section">
                    <table class="qr-table">
                        <tr>
                            <td style="width: 48px; text-align: center;">
                                <img src="{{ $qr }}" style="width: 42px; height: 42px;" alt="QR Code">
                            </td>
                            <td style="padding-left: 6px; font-size: 6px; color: #6b7280; line-height: 1.2;">
                                Scan to verify challan authenticity.<br>
                                Pay at any branch or school accounts office.<br>
                                Keep this slip stamped for school records.
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Signatures -->
                <table class="signatures-table">
                    <tr>
                        <td>Depositor's Signature</td>
                        <td>Bank Cashier / Officer Stamp</td>
                    </tr>
                </table>
            </div>
        </td>
        @endforeach
    </tr>
</table>

</body>
</html>

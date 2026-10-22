<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Salary Slip</title>
<style>
@page{margin:32px}body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111;margin:0}h1{text-align:center;font-size:20px;margin:0 0 5px}.subtitle{text-align:center;margin:0 0 20px}table{width:100%;border-collapse:collapse;table-layout:fixed}.details{border:1px solid #333;border-bottom:0}.details td{width:50%;padding:14px 12px;vertical-align:top}.detail{margin:0 0 8px;line-height:1.6;overflow-wrap:break-word}.detail:last-child{margin-bottom:0}.label{font-weight:bold}.ledger{border:1px solid #333}.ledger th{padding:10px 12px;font-size:12px;text-transform:uppercase;border-bottom:1px solid #333}.ledger th,.ledger td{border-right:1px solid #333}.ledger th:first-child,.ledger td:first-child{width:48%;text-align:left}.ledger th:not(:first-child),.ledger td:not(:first-child){width:26%;text-align:center}.ledger td{padding:10px 12px;font-weight:bold}.ledger tbody tr:last-child td{padding-bottom:20px}.ledger .total td{border-top:1px solid #333;padding:10px 12px}.payment{border:1px solid #333;border-top:0}.payment td{vertical-align:top}.bank{width:48%;padding:14px 12px;border-right:1px solid #333}.net{width:52%;padding:0}.net-heading{text-align:center;font-size:14px;font-weight:bold;border-bottom:1px solid #333;padding:9px}.net-amount{text-align:center;font-size:19px;font-weight:bold;border-bottom:1px solid #333;padding:12px}.net-status{text-align:center;padding:12px;font-size:10px}.signatures{margin-top:38px}.signatures td{width:50%;font-size:10px}.signatures td:last-child{text-align:right}.note{font-size:9px;color:#555;margin-top:22px}
</style></head><body>
@php
    $teacher = $payroll->teacher;
    $salary = $teacher->salaryStructure;
    $earnings = (int) round($payroll->basic_salary * 100) + (int) round($payroll->allowances * 100);
    $deductions = (int) round($payroll->deductions * 100) + (int) round($payroll->absence_deduction * 100) + (int) round($payroll->advances * 100);
@endphp
<h1>School Management &middot; Salary Slip</h1>
<p class="subtitle">{{ $payroll->month->format('F Y') }} | All amounts in PKR</p>
<table class="details"><tr><td>
<p class="detail"><span class="label">Name of Employee :</span> {{ $teacher->name }}</p>
<p class="detail"><span class="label">Employee ID :</span> {{ $teacher->employee_id }}</p>
<p class="detail"><span class="label">Salary Month :</span> {{ $payroll->month->format('F Y') }}</p>
</td><td>
<p class="detail"><span class="label">Designation :</span> {{ $teacher->designation?->name ?: 'Not provided' }}</p>
<p class="detail"><span class="label">Department :</span> {{ $teacher->department?->name ?: 'Not provided' }}</p>
<p class="detail"><span class="label">Payment Date :</span> {{ $payroll->paid_at?->format('M d, Y') ?: 'Not paid yet' }}</p>
</td></tr></table>
<table class="ledger"><thead><tr><th>Description</th><th>Earnings</th><th>Deduction</th></tr></thead><tbody>
<tr><td>Basic Salary</td><td>{{ number_format($payroll->basic_salary, 2) }}</td><td>&mdash;</td></tr>
<tr><td>Allowances</td><td>{{ number_format($payroll->allowances, 2) }}</td><td>&mdash;</td></tr>
<tr><td>Fixed Deductions</td><td>&mdash;</td><td>{{ number_format($payroll->deductions, 2) }}</td></tr>
<tr><td>Absence Deduction ({{ number_format($payroll->unpaid_days, 2) }} days)</td><td>&mdash;</td><td>{{ number_format($payroll->absence_deduction, 2) }}</td></tr>
<tr><td>Salary Advances</td><td>&mdash;</td><td>{{ number_format($payroll->advances, 2) }}</td></tr>
</tbody><tfoot><tr class="total"><td>TOTAL</td><td>{{ number_format($earnings / 100, 2) }}</td><td>{{ number_format($deductions / 100, 2) }}</td></tr></tfoot></table>
<table class="payment"><tr><td class="bank">
<p class="detail"><span class="label">Bank Name :</span> {{ $salary?->bank_name ?: 'Not provided' }}</p>
<p class="detail"><span class="label">Employee Name :</span> {{ $teacher->name }}</p>
<p class="detail"><span class="label">Bank A/C Number :</span> {{ $salary?->account_no ?: 'Not provided' }}</p>
<p class="detail"><span class="label">Payment Method :</span> {{ $payroll->payment_method ?: 'Not recorded' }}</p>
</td><td class="net"><div class="net-heading">Net Pay</div><div class="net-amount">PKR {{ number_format($payroll->net_salary, 2) }}</div><div class="net-status">Payment Status: <strong>{{ $payroll->status }}</strong></div></td></tr></table>
<table class="signatures"><tr><td>Employee Signature: __________________</td><td>Authorized Signature: __________________</td></tr></table>
<p class="note">Net Pay = Basic Salary + Allowances &minus; Fixed Deductions &minus; Absence Deduction &minus; Salary Advances.</p>
</body></html>

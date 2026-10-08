@extends('layouts.admin')
@section('title', 'Fee '.$module)
@section('content')
@include('fees.billing.header', ['title' => $module])
@if($module === 'Vouchers')
<details class="panel student-form" open><summary><strong>Generate Monthly Vouchers</strong></summary>
<form method="POST" action="{{ route('fees.vouchers.store') }}" style="margin-top:18px">@csrf
<div class="student-form-grid">
<div class="admission-field"><label for="bill-session">Academic Session</label><select id="bill-session" name="academic_session_id" required>@foreach($sessions as $session)<option value="{{ $session->id }}" @selected(old('academic_session_id', $sessions->first()?->id) == $session->id)>{{ $session->name }}</option>@endforeach</select></div>
<div class="admission-field"><label for="bill-class">Class</label><select id="bill-class" name="school_class_id"><option value="">All classes</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected(old('school_class_id') == $class->id)>{{ $class->name }}</option>@endforeach</select></div>
<div class="admission-field"><label for="bill-month">Month</label><select id="bill-month" name="month">@foreach(range(1,12) as $month)<option value="{{ $month }}" @selected(old('month', today()->month) == $month)>{{ \Carbon\Carbon::create(2026,$month,1)->format('F') }}</option>@endforeach</select></div>
<div class="admission-field"><label for="bill-year">Year</label><input id="bill-year" name="year" type="number" min="1900" max="9999" value="{{ old('year', today()->year) }}" required></div>
<div class="admission-field student-address" data-fee-student-lookup data-url="{{ route('fees.setup.students.search') }}"><label for="bill-student-search">Single student (optional)</label><input id="bill-student-search" type="search" data-fee-student-search placeholder="Search a student, or leave empty for the selected class"><select name="student_id" data-fee-student-options><option value="">All active students in selected class/session</option></select><small data-fee-search-message role="status">Search by name or admission number.</small></div>
</div><p class="photo-help">Existing vouchers are kept. Discounts and fees are saved as a snapshot; changing fee setup will not change issued vouchers. Older unpaid vouchers remain separate balances.</p><div class="form-actions"><button class="button-primary">Generate Vouchers</button></div>
</form></details>
@endif
@isset($outstanding)<div class="panel student-form"><strong>Total overdue: PKR {{ number_format($outstanding / 100, 2) }}</strong></div>@endisset
<section class="panel student-panel fee-voucher-list" style="margin-top:20px">
<form method="GET" class="library-toolbar fee-voucher-filters" data-auto-filters>
<div class="library-search"><x-icon name="search" size="16"/><input type="search" name="search" value="{{ request('search') }}" placeholder="Voucher, student or admission no..."></div>
<label class="library-status-filter">Class<select name="class_id"><option value="">All classes</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>@endforeach</select></label>
<label class="library-status-filter">Month<input type="month" name="month" value="{{ request('month') }}"></label>
@if($module === 'Vouchers')<label class="library-status-filter">Status<select name="status">@foreach(['All','Unpaid','Partial','Paid','Overdue','Cancelled'] as $status)<option @selected(request('status', 'All') === $status)>{{ $status }}</option>@endforeach</select></label>@endif
<a class="button-secondary fee-filter-clear" href="{{ url()->current() }}">Clear</a>@if($module === 'Defaulters')<a class="button-secondary csv-export-button" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Export CSV</a>@endif
</form>
<div class="student-table-scroll"><table class="student-table" style="min-width:850px;width:100%"><thead><tr><th>Voucher</th><th>Student / Class</th><th>Month</th><th>Due date</th><th>Fee</th><th>Paid</th><th>Balance</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($vouchers as $voucher)<tr><td>{{ $voucher->number }}</td><td>{{ $voucher->student_name }}<small style="display:block">{{ $voucher->admission_no }} / {{ $voucher->class_name }}</small></td><td>{{ sprintf('%04d-%02d', $voucher->year, $voucher->month) }}</td><td>{{ $voucher->due_date->format('d M Y') }}</td><td>{{ number_format($voucher->total_cents / 100, 2) }}</td><td>{{ number_format($voucher->payments->whereNull('reversed_at')->sum('amount_cents') / 100, 2) }}</td><td>{{ number_format($voucher->balance_cents / 100, 2) }}</td><td><span class="badge fee-voucher-status fee-status-{{ strtolower($voucher->display_status) }}">{{ $voucher->display_status }}</span></td><td><a class="button-secondary fee-voucher-view" href="{{ route('fees.vouchers.show', $voucher) }}">{{ $module === 'Collection' ? 'Receive Payment' : 'View' }}</a></td></tr>
@empty<tr><td colspan="9" style="text-align:center;padding:35px">No {{ strtolower($module) }} records found.</td></tr>@endforelse
</tbody></table></div><div class="student-pagination">{{ $vouchers->links() }}</div>
</section>
@isset($payments)<section class="panel student-form" style="margin-top:20px"><h2>Recent Receipts</h2>@include('fees.billing.payments-table')</section>@endisset
@endsection

@extends('layouts.admin')
@section('title', request()->routeIs('accounts.reports.*') ? 'Accounts Reports' : 'Transactions')
@section('content')
<div class="page-heading"><div><div class="breadcrumb">Accounts</div><h1>{{ request()->routeIs('accounts.reports.*') ? 'Accounts Reports' : 'Transactions' }}</h1><p>Collected fees, other income, expenses and paid salaries appear automatically.</p></div></div>
@include('accounts.nav')
@include('students.partials.feedback')
<form method="GET" class="panel student-form"><div class="student-form-grid">
<div class="admission-field"><label for="from">From</label><input id="from" type="date" name="from" value="{{ request('from') }}"></div>
<div class="admission-field"><label for="to">To</label><input id="to" type="date" name="to" value="{{ request('to') }}"></div>
<div class="admission-field"><label for="type">Type</label><select id="type" name="type"><option value="">All types</option>@foreach(['Income','Expense','Salary'] as $type)<option @selected(request('type') === $type)>{{ $type }}</option>@endforeach</select></div>
</div><div class="form-actions"><button class="button-primary">Apply Filters</button><button class="button-secondary" name="export" value="csv">Export CSV</button><a class="button-secondary" href="{{ url()->current() }}">Reset</a></div></form>
<section class="panel"><div class="student-table-scroll"><table class="student-table"><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Category</th><th>Amount (PKR)</th><th>Payment Method</th><th>Party / Reference</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td>{{ $row->date }}</td><td>{{ $row->type }}</td><td>{{ $row->description }}</td><td>{{ $row->category }}</td><td>{{ $row->signed_cents >= 0 ? '+' : '−' }}{{ number_format(abs($row->signed_cents) / 100, 2) }}</td><td>{{ $row->payment_method ?: 'Unavailable' }}</td><td>{{ $row->party ?: '—' }}</td></tr>@empty<tr><td colspan="7">No transactions match these filters.</td></tr>@endforelse
</tbody></table></div><div class="student-pagination">{{ $rows->links() }}</div></section>
@endsection

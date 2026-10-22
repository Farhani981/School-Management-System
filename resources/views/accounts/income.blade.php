@extends('layouts.admin')
@section('title', 'Income')
@section('content')
<div class="page-heading"><div><div class="breadcrumb">Accounts / Income</div><h1>Income</h1><p>Record admission income, donations and miscellaneous income. Student fee receipts already appear in Transactions.</p></div><a class="button-secondary" href="{{ route('fees.collection.index') }}">Fee Collection</a></div>
@include('accounts.nav')
@include('students.partials.feedback')
<details id="income-entry" @if($errors->any()) open @endif>
<summary class="button-primary" style="list-style:none;cursor:pointer;margin-bottom:20px">+ Income</summary>
<form method="POST" action="{{ route('accounts.income.store') }}" enctype="multipart/form-data" class="panel student-form">@csrf
<h2 style="margin-bottom:20px">Add Income</h2>
<div class="student-form-grid">
@foreach(['title'=>'Income Title', 'category'=>'Income Category', 'received_from'=>'Received From', 'reference'=>'Reference'] as $field=>$label)
<div class="admission-field"><label for="income-{{ $field }}">{{ $label }}</label><input id="income-{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" maxlength="{{ $field === 'category' ? 100 : ($field === 'reference' ? 150 : 255) }}" @if($field !== 'reference') required @endif @if($field === 'category') list="income-categories" @endif></div>
@endforeach
<datalist id="income-categories">@foreach(['Admission Income','Donation','Miscellaneous Income','Other Income'] as $category)<option value="{{ $category }}">@endforeach</datalist>
<div class="admission-field"><label for="income-amount">Amount (PKR)</label><input id="income-amount" name="amount" type="number" min="0.01" max="99999999.99" step="0.01" value="{{ old('amount') }}" required></div>
<div class="admission-field"><label for="income-date">Date</label><input id="income-date" name="income_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('income_date', today()->toDateString()) }}" required></div>
<div class="admission-field"><label for="income-method">Payment Method</label><select id="income-method" name="payment_method" required>@foreach(['Cash','Bank Transfer','Cheque'] as $method)<option @selected(old('payment_method') === $method)>{{ $method }}</option>@endforeach</select></div>
<div class="admission-field"><label for="income-description">Description</label><textarea id="income-description" name="description" maxlength="5000">{{ old('description') }}</textarea></div>
<div class="admission-field"><label for="income-attachment">Receipt / Attachment (PDF, JPG, PNG; max 5 MB)</label><input id="income-attachment" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></div>
</div><div class="form-actions"><button class="button-primary">Save Income</button></div></form>
</details>
<section class="panel"><div class="student-table-scroll"><table class="student-table"><thead><tr><th>Date</th><th>Title</th><th>Category</th><th>Amount (PKR)</th><th>Method</th><th>Received From</th><th>Description</th><th>Receipt</th></tr></thead><tbody>
@forelse($incomes as $income)<tr><td>{{ $income->income_date->format('d M Y') }}</td><td>{{ $income->title }}</td><td>{{ $income->category }}</td><td>{{ number_format($income->amount_cents / 100, 2) }}</td><td>{{ $income->payment_method }}</td><td>{{ $income->received_from }}</td><td>{{ $income->description }}</td><td>@if($income->attachment_path)<a href="{{ route('accounts.attachments.show', ['income', $income->id]) }}">Download</a>@else — @endif</td></tr>@empty<tr><td colspan="8">No other income recorded yet.</td></tr>@endforelse
</tbody></table></div><div class="student-pagination">{{ $incomes->links() }}</div></section>
@endsection

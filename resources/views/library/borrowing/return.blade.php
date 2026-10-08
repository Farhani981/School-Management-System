@extends('layouts.admin')
@section('title', 'Receive Return')
@section('content')
<div class="library-page">
    <div class="page-heading"><div><div class="breadcrumb">Library / Issue Return / Receive Return</div><h1>Receive Return</h1><p>{{ $transaction->book->title }} · {{ $transaction->copy->accession_number }}</p></div><a class="button-secondary" href="{{ route('library.borrowing.show', $transaction) }}">Back to Loan</a></div>
    @include('subjects.partials.feedback')
    <section class="panel library-return-overview"><dl class="student-profile-grid">
        @foreach(['Member' => $transaction->member->name, 'Member ID' => $transaction->member->member_id, 'Issue Date' => $transaction->issue_date->format('d M Y'), 'Due Date' => $transaction->due_date->format('d M Y'), 'Days Late Today' => $transaction->days_late, 'Copy' => $transaction->copy->accession_number] as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach
    </dl></section>
    <form class="panel student-form" method="POST" action="{{ route('library.borrowing.receive', $transaction) }}">
        @csrf
        <fieldset class="admission-section"><legend>Return Information</legend><div class="student-form-grid">
            <div class="admission-field"><label for="return-date">Return / Closure Date <span class="required-mark">*</span></label><input id="return-date" name="return_date" type="date" value="{{ old('return_date', today()->format('Y-m-d')) }}" min="{{ $transaction->issue_date->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" required></div>
            <div class="admission-field"><label for="return-condition">Condition / Outcome <span class="required-mark">*</span></label><select id="return-condition" name="condition" required>@foreach([...\App\Models\LibraryBookCopy::CONDITIONS, 'Lost'] as $condition)<option value="{{ $condition }}" @selected(old('condition', $transaction->copy->condition) === $condition)>{{ $condition === 'Lost' ? 'Lost — close loan without receiving copy' : $condition }}</option>@endforeach</select><p class="photo-help">Damaged and lost copies stay unavailable for borrowing.</p></div>
            <div class="admission-field student-address"><label for="return-notes">Return Notes (optional)</label><textarea id="return-notes" name="return_notes" rows="3" maxlength="2000">{{ old('return_notes') }}</textarea></div>
        </div></fieldset>
        <div class="form-actions"><a class="button-secondary" href="{{ route('library.borrowing.show', $transaction) }}">Cancel</a><button class="button-primary">Save Return</button></div>
    </form>
</div>
@endsection

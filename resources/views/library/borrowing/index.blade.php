@extends('layouts.admin')
@section('title', 'Issue / Return')
@section('content')
<div class="library-page">
    <div class="page-heading"><div><div class="breadcrumb">Library / Issue Return</div><h1>Issue / Return</h1><p>Issue available copies, receive returns and track due dates.</p></div><a class="button-primary" href="{{ route('library.borrowing.create') }}">+ Issue Book</a></div>
    @include('subjects.partials.feedback')
    <div class="library-loan-summary"><a href="{{ route('library.borrowing.index', ['status' => 'Issued']) }}"><span>Currently Issued</span><strong>{{ $issued }}</strong></a><a href="{{ route('library.borrowing.index', ['status' => 'Overdue']) }}"><span>Overdue</span><strong>{{ $overdue }}</strong></a><a href="{{ route('library.borrowing.index', ['status' => 'Returned']) }}"><span>Returned</span><strong>{{ $returned }}</strong></a></div>
    <section class="panel library-directory">
        <form method="GET" class="library-toolbar library-borrowing-toolbar" data-auto-filters>
            @if(!empty($filters['member_id']))<input type="hidden" name="member_id" value="{{ $filters['member_id'] }}">@endif
            <div class="library-search"><x-icon name="search" size="16"/><input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Member, book or copy number..." aria-label="Search borrowing records"></div>
            <label class="library-status-filter" for="loan-status">Status<select id="loan-status" name="status">@foreach(['Issued' => 'Currently issued', 'Overdue' => 'Overdue', 'Returned' => 'Returned', 'Lost' => 'Lost', 'All' => 'All records'] as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select></label>
            <a class="button-secondary" href="{{ route('library.borrowing.index') }}">Clear</a>
            <label class="library-page-size">Rows per page: <select name="per_page">@foreach([10,25,50] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 10) == $size)>{{ $size }}</option>@endforeach</select></label>
        </form>
        @include('library.borrowing.partials.table')
    </section>
</div>
@endsection

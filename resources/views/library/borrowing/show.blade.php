@extends($layout)
@section('title', 'Loan Details')
@section('content')
<div class="library-page">
    <div class="page-heading"><div><div class="breadcrumb">Library / Loan Details</div><h1>Loan Details</h1><p>{{ $transaction->book->title }} · {{ $transaction->copy->accession_number }}</p></div><div class="student-actions">
        @can('viewAny', \App\Models\LibraryTransaction::class)<a class="button-secondary" href="{{ route('library.borrowing.index') }}">Back to Issue / Return</a>@else<a class="button-secondary" href="{{ route('library.members.show', $transaction->member) }}">My Membership</a>@endcan
        @can('receive', $transaction) @if(!$transaction->return_date)<a class="button-primary" href="{{ route('library.borrowing.return', $transaction) }}">Receive Return</a>@endif @endcan
    </div></div>
    @include('subjects.partials.feedback')
    <section class="panel"><div class="panel-heading"><h2>Borrowing Record #{{ $transaction->id }}</h2><span class="library-loan-status status-{{ strtolower($transaction->display_status) }}">{{ $transaction->display_status }}</span></div>
        <dl class="student-profile-grid">
            @foreach(['Member' => $transaction->member->name, 'Member ID' => $transaction->member->member_id, 'Book' => $transaction->book->title,
                'Copy / Accession No' => $transaction->copy->accession_number, 'Barcode' => $transaction->copy->barcode,
                'Issue Date' => $transaction->issue_date->format('d M Y'), 'Due Date' => $transaction->due_date->format('d M Y'),
                $transaction->status === 'Lost' ? 'Closure Date' : 'Return Date' => $transaction->return_date?->format('d M Y') ?? 'Not returned',
                'Days Late' => $transaction->days_late, 'Return Condition' => $transaction->return_condition ?? 'Not returned',
                'Issued By' => $transaction->issuer->name, 'Received / Closed By' => $transaction->receiver?->name ?? 'Not received'] as $label => $value)
                <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
        <div class="library-description"><h3>Issue Notes</h3><p>{{ $transaction->notes ?? 'No notes.' }}</p>@if($transaction->return_date)<h3>Return Notes</h3><p>{{ $transaction->return_notes ?? 'No notes.' }}</p>@endif</div>
    </section>
</div>
@endsection

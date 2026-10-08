@extends($layout)
@section('title', 'Member Details')
@section('content')
<div class="library-page">
    <div class="page-heading"><div><div class="breadcrumb">Library / Member Details</div><h1>Member Details</h1><p>{{ $member->name }} · {{ $member->member_id }}</p></div><div class="student-actions">
        @can('viewAny', \App\Models\LibraryMember::class)<a class="button-secondary" href="{{ route('library.members.index') }}">Back to Members</a>@else<a class="button-secondary" href="{{ route('library.books.index') }}">Books List</a>@endcan
        @can('update', $member)<a class="button-primary" href="{{ route('library.members.edit', $member) }}">Edit Member</a>@endcan
        @can('create', \App\Models\LibraryTransaction::class) @if($member->status === 'Active')<a class="button-primary" href="{{ route('library.borrowing.create', ['member_id' => $member->id]) }}">Issue Book</a>@endif @endcan
    </div></div>
    @include('subjects.partials.feedback')
    <section class="panel"><div class="panel-heading"><h2>Member Information</h2><span class="student-status {{ $member->status === 'Active' ? 'status-active' : 'status-inactive' }}">{{ $member->status }}</span></div>
        <dl class="student-profile-grid">
            @foreach(['Member ID' => $member->member_id, 'Member Name' => $member->name, 'Member Type' => $member->member_type,
                $member->member_type === 'Student' ? 'Admission No' : 'Employee ID' => $member->reference,
                $member->member_type === 'Student' ? 'Class / Section' : 'Department' => $member->context,
                'Phone' => $member->person?->phone, 'Email' => $member->person?->email,
                'Registration Date' => $member->registration_date->format('d M Y'), 'Borrowing Limit' => $member->borrowing_limit.' books'] as $label => $value)
                <div><dt>{{ $label }}</dt><dd>{{ $value ?: 'Not provided' }}</dd></div>
            @endforeach
        </dl>
    </section>
    <section class="panel"><div class="panel-heading"><h2>Borrowing History</h2></div>@include('library.borrowing.partials.table')</section>
</div>
@endsection

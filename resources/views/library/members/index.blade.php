@extends('layouts.admin')
@section('title', 'Members List')
@section('content')
<div class="library-page">
    <div class="page-heading">
        <div><div class="breadcrumb">Library / Members List</div><h1>Members List</h1><p>Student and teacher library memberships.</p></div>
        @can('create', \App\Models\LibraryMember::class)<a class="button-primary" href="{{ route('library.members.create') }}">+ Add Member</a>@endcan
    </div>
    @include('subjects.partials.feedback')
    <section class="panel library-directory">
        <form method="GET" class="library-toolbar">
            <div class="library-search"><x-icon name="search" size="16"/><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or member ID..." aria-label="Search members"></div>
            <details class="library-filters"><summary class="button-secondary">Filters</summary><div class="library-filter-fields">
                <label>Member Type<select name="member_type"><option value="">All types</option>@foreach(\App\Models\LibraryMember::TYPES as $type)<option @selected(($filters['member_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></label>
                <label>Status<select name="status"><option value="">All statuses</option>@foreach(\App\Models\LibraryMember::STATUSES as $status)<option @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></label>
            </div></details>
            <button class="button-secondary">Search</button><a href="{{ route('library.members.index') }}">Clear</a>
            <label class="library-page-size">Rows per page: <select name="per_page">@foreach([10,25,50] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 10) == $size)>{{ $size }}</option>@endforeach</select></label>
        </form>
        <div class="student-table-scroll"><table class="student-table library-member-table">
            <thead><tr><th>S.L</th><th>Member ID</th><th>Member Name</th><th>Type</th><th>Class / Department</th><th>Phone</th><th>Registration Date</th><th>Borrowing Limit</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($members as $member)
                <tr>
                    <td>{{ str_pad($members->firstItem() + $loop->index, 2, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $member->member_id }}</td>
                    <td><a href="{{ route('library.members.show', $member) }}">{{ $member->name }}</a><span class="library-member-reference">{{ $member->reference }}</span></td>
                    <td>{{ $member->member_type }}</td><td>{{ $member->context }}</td><td>{{ $member->person?->phone ?? 'Not set' }}</td>
                    <td>{{ $member->registration_date->format('d M Y') }}</td><td>{{ $member->borrowing_limit }} books</td>
                    <td><span class="student-status {{ $member->status === 'Active' ? 'status-active' : 'status-inactive' }}">{{ $member->status }}</span></td>
                    <td><details class="library-row-menu"><summary aria-label="Actions for {{ $member->name }}">&#8942;</summary><div>
                        <a href="{{ route('library.members.show', $member) }}">Member Details</a>
                        @can('update', $member)<a href="{{ route('library.members.edit', $member) }}">Edit Member</a>@endcan
                    </div></details></td>
                </tr>
            @empty
                <tr><td colspan="10"><div class="students-empty"><h3>No members found</h3><p>Add a student or teacher member, or adjust your filters.</p></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="student-pagination">{{ $members->links() }}<span>{{ $members->total() }} members</span></div>
    </section>
</div>
@endsection

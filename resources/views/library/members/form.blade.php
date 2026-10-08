@extends('layouts.admin')
@section('title', $member->exists ? 'Edit Member' : 'Add Member')
@section('content')
<div class="library-page">
    <div class="page-heading"><div><div class="breadcrumb">Library / Members List</div><h1>{{ $member->exists ? 'Edit Member' : 'Add Member' }}</h1><p>Choose an existing student or teacher and set their library membership.</p></div><a class="button-secondary" href="{{ route('library.members.index') }}">Back to Members</a></div>
    @include('subjects.partials.feedback')
    <form class="panel student-form" method="POST" action="{{ $member->exists ? route('library.members.update', $member) : route('library.members.store') }}" @if(!$member->exists) data-library-member-form data-borrowing-limits='@json(config("library.borrowing_limits"))' @endif>
        @csrf @if($member->exists) @method('PUT') @endif
        <fieldset class="admission-section"><legend>Member Information</legend><div class="student-form-grid">
            @if($member->exists)
                <div class="admission-field"><label>Member</label><p class="library-member-identity">{{ $member->name }}<span>{{ $member->member_id }} · {{ $member->member_type }} · {{ $member->reference }}</span></p></div>
            @else
                @php $selectedType = old('member_type', $member->member_type); @endphp
                <div class="admission-field"><label for="member-type">Member Type <span class="required-mark">*</span></label><select id="member-type" name="member_type" data-member-type required>@foreach(\App\Models\LibraryMember::TYPES as $type)<option @selected($selectedType === $type)>{{ $type }}</option>@endforeach</select></div>
                <div class="admission-field" data-member-person="Student" @if($selectedType !== 'Student') hidden @endif>
                    <label for="member-student">Student <span class="required-mark">*</span></label>
                    <select id="member-student" name="student_id" @disabled($selectedType !== 'Student') @required($selectedType === 'Student')><option value="">Select a student</option>@foreach($students as $student)<option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>{{ $student->full_name }} · {{ $student->admission_no }}{{ $student->schoolClass ? ' · '.$student->schoolClass->name : '' }}{{ $student->section ? ' / '.$student->section->name : '' }}</option>@endforeach</select>
                    @if($students->isEmpty())<p class="photo-help">No active students without a membership are available.</p>@endif
                </div>
                <div class="admission-field" data-member-person="Teacher" @if($selectedType !== 'Teacher') hidden @endif>
                    <label for="member-teacher">Teacher <span class="required-mark">*</span></label>
                    <select id="member-teacher" name="teacher_id" @disabled($selectedType !== 'Teacher') @required($selectedType === 'Teacher')><option value="">Select a teacher</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(old('teacher_id') == $teacher->id)>{{ $teacher->name }} · {{ $teacher->employee_id }}</option>@endforeach</select>
                    @if($teachers->isEmpty())<p class="photo-help">No active teachers without a membership are available.</p>@endif
                </div>
            @endif
            <div class="admission-field"><label for="member-date">Registration Date <span class="required-mark">*</span></label><input id="member-date" name="registration_date" type="date" value="{{ old('registration_date', $member->registration_date?->format('Y-m-d')) }}" required></div>
            <div class="admission-field"><label for="member-limit">Borrowing Limit <span class="required-mark">*</span></label><input id="member-limit" name="borrowing_limit" data-member-limit type="number" min="1" max="100" value="{{ old('borrowing_limit', $member->exists ? $member->borrowing_limit : config('library.borrowing_limits.'.old('member_type', $member->member_type))) }}" required><p class="photo-help">Maximum books this member may borrow at one time.</p></div>
            <div class="admission-field"><label for="member-status">Status</label><select id="member-status" name="status">@foreach(\App\Models\LibraryMember::STATUSES as $status)<option @selected(old('status', $member->status) === $status)>{{ $status }}</option>@endforeach</select></div>
        </div></fieldset>
        <div class="form-actions"><a class="button-secondary" href="{{ route('library.members.index') }}">Cancel</a><button class="button-primary">Save Member</button></div>
    </form>
</div>
@endsection

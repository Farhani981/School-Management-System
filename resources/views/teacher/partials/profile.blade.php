@php
    $teacher?->loadMissing(['department', 'designation']);
    $profileName = $teacher?->name ?: auth()->user()->name;
    $initials = collect(preg_split('/\s+/', trim($profileName)))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp
<section class="tp-hero" aria-label="Teacher profile">
    <div class="tp-cover"><span><x-icon name="school" size="18"/> SCHOOL FACULTY</span></div>
    <div class="tp-identity">
        <div class="tp-avatar">
            @if($teacher?->photo)<img src="{{ asset('storage/'.$teacher->photo) }}" alt="{{ $profileName }}" data-teacher-avatar><span hidden>{{ $initials }}</span>@else<span>{{ $initials }}</span>@endif
        </div>
        <div class="tp-name"><p>TEACHER PROFILE</p><h2>{{ $profileName }}</h2><div>{{ $teacher?->designation?->name ?: 'Teacher' }} @if($teacher?->department)&middot; {{ $teacher->department->name }}@endif</div></div>
        <div class="tp-identity-meta"><span class="tp-status"><i></i>{{ $teacher?->status ?: 'Account linked' }}</span><span>{{ $teacher?->employee_id ?: 'Employee ID not assigned' }}</span></div>
    </div>
</section>
@if(!$teacher)<div class="tp-card"><p>No teacher record linked yet. Contact the school administrator to complete your profile.</p></div>@endif
<div class="tp-layout">
    <div class="tp-main">
        <section class="tp-card"><div class="tp-card-heading"><span class="tp-section-icon"><x-icon name="users" size="19"/></span><div><h3>Personal Information</h3><p>Your contact and personal details</p></div></div>
            <dl class="tp-details">
                @foreach(['Full name' => $profileName, 'Email address' => auth()->user()->email, 'Phone number' => $teacher?->phone, 'Gender' => $teacher?->gender, 'Date of birth' => $teacher?->date_of_birth?->format('d M Y'), 'Emergency contact' => $teacher?->emergency_contact] as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value ?: 'Not added yet' }}</dd></div>@endforeach
                <div class="tp-wide"><dt>Address</dt><dd>{{ $teacher?->address ?: 'Not added yet' }}</dd></div>
            </dl>
        </section>
        <section class="tp-card"><div class="tp-card-heading"><span class="tp-section-icon"><x-icon name="book" size="19"/></span><div><h3>Qualifications & Experience</h3><p>Your professional background</p></div></div>
            <dl class="tp-details"><div><dt>Qualification</dt><dd>{{ $teacher?->qualification ?: 'Not added yet' }}</dd></div><div><dt>Teaching experience</dt><dd>{{ $teacher?->experience ?: 'Not added yet' }}</dd></div></dl>
        </section>
    </div>
    <aside class="tp-side">
        <section class="tp-card"><div class="tp-card-heading"><span class="tp-section-icon"><x-icon name="teacher" size="19"/></span><div><h3>School Details</h3><p>Your role at school</p></div></div>
            <dl class="tp-details tp-single">@foreach(['Employee ID' => $teacher?->employee_id, 'Department' => $teacher?->department?->name, 'Designation' => $teacher?->designation?->name, 'Joining date' => $teacher?->joining_date?->format('d M Y')] as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value ?: 'Not added yet' }}</dd></div>@endforeach</dl>
        </section>
        <section class="tp-card tp-shortcuts"><h3>Your Workspace</h3><a href="{{ route('teacher.classes.index') }}"><x-icon name="classes" size="18"/><span>My Classes</span><x-icon name="chevron" size="14"/></a><a href="{{ route('teacher.timetable.index') }}"><x-icon name="clock" size="18"/><span>My Daily Routine</span><x-icon name="chevron" size="14"/></a><p>To update your profile details, contact the school administrator.</p></section>
    </aside>
</div>

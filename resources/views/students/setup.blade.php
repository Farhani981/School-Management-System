@extends('layouts.admin')
@section('title', 'Academic Setup')
@section('content')
<div class="page-heading"><div><h1>Academic Setup</h1><p>Manage the classes, sections and sessions used for admissions and promotions.</p></div><a class="button-secondary" href="{{ route('students.index') }}">Students</a></div>
@include('students.partials.feedback')
<section class="panel academic-current"><div class="panel-heading"><div><h2>Current academic session</h2><p>Select the session used for dashboard totals and student records.</p></div><span class="badge {{ $currentSession ? 'badge-green' : 'badge-neutral' }}">{{ $options['academic_session_id'][$currentSession] ?? 'Not set' }}</span></div><div class="student-form">
@if(auth()->check() && \App\Policies\AttendancePolicy::admin(auth()->user()))
<form method="POST" action="{{ route('academic-setup.current') }}" class="academic-inline-form">@csrf<div class="admission-field"><label for="current-session">Academic session</label><select id="current-session" name="academic_session_id" required><option value="">Select current session</option>@foreach($options['academic_session_id'] as $id=>$name)<option value="{{ $id }}" @selected((string)$currentSession===(string)$id)>{{ $name }}</option>@endforeach</select></div><button class="button-primary">Set Current Session</button></form>
@else<p>Current session: {{ $options['academic_session_id'][$currentSession] ?? 'Not set' }}. Sign in as an Admin to change it.</p>@endif
</div></section>
<div class="academic-setup-grid admin-academic-grid">
@foreach(['class_id' => ['class', 'Classes'], 'section_id' => ['section', 'Sections'], 'academic_session_id' => ['session', 'Academic Sessions']] as $field => [$type, $label])
<section class="panel academic-setup-card {{ $type === 'class' ? 'admin-classes-module' : '' }}"><div class="panel-heading"><div><h2>{{ $label }}</h2><p>{{ count($options[$field]) }} {{ strtolower($label) }} configured</p></div><span class="badge badge-neutral">{{ count($options[$field]) }}</span></div>
@if($type === 'class')
<div class="admin-classes-toolbar"><p>Active students &middot; {{ $options['academic_session_id'][$currentSession] ?? 'All academic sessions' }}</p><div class="admin-classes-search"><x-icon name="search" size="16"/><label class="sr-only" for="admin-class-search">Search classes, sections or sessions</label><input id="admin-class-search" type="search" placeholder="Search class or section..." autocomplete="off"></div></div>
<div class="admin-classes-grid">
@forelse($options[$field] as $id => $name)
@php
    $stats = $classStudents->get($id);
    $filters = array_filter(['class_id' => $id, 'academic_session_id' => $currentSession]);
    $sections = $classSections->get($id, collect())->filter(fn ($sectionId) => isset($options['section_id'][$sectionId]));
@endphp
<article class="admin-class-card" data-admin-class-name="{{ mb_strtolower($name.' '.$sections->map(fn ($sectionId) => $options['section_id'][$sectionId])->join(' ').' '.($options['academic_session_id'][$currentSession] ?? '')) }}">
<div class="admin-class-heading"><span class="admin-class-avatar"><x-icon name="classes" size="22"/></span><div><span class="admin-class-eyebrow">Academic class</span><h3>{{ $name }}</h3></div></div>
<div class="admin-class-session"><span class="admin-class-meta-label"><x-icon name="calendar" size="14"/> Session</span><span class="admin-session-badge">{{ $options['academic_session_id'][$currentSession] ?? 'All sessions' }}</span></div>
<div class="admin-class-sections"><span class="admin-class-meta-label"><x-icon name="classes" size="14"/> Sections</span><div class="admin-section-list">@forelse($sections as $sectionId)<a class="admin-section-chip" href="{{ route('students.index', $filters + ['section_id' => $sectionId]) }}" aria-label="View {{ $name }}, section {{ $options['section_id'][$sectionId] }} students">{{ $options['section_id'][$sectionId] }}</a>@empty<span class="admin-section-empty">No sections assigned</span>@endforelse</div></div>
<div class="admin-class-stats"><div><strong>{{ $stats?->student_count ?? 0 }}</strong><span>Students</span></div><div><strong>{{ $sections->count() }}</strong><span>Sections</span></div><div><strong>{{ $classTeachers->get($id, 0) }}</strong><span>Teachers</span></div></div>
<div class="admin-class-actions"><a class="button-primary" href="{{ route('students.index', $filters) }}"><x-icon name="users" size="15"/> View Students</a><a class="button-secondary" href="{{ route('subjects.classes.index', ['school_class_id' => $id]) }}"><x-icon name="book" size="15"/> Subjects</a></div>
<div class="admin-class-management"><a class="button-secondary" href="{{ route('academic-setup.classes.edit', $id) }}" aria-label="Edit {{ $name }}">Edit Class</a><form method="POST" action="{{ route('academic-setup.classes.destroy', $id) }}" onsubmit="return confirm('Delete this class? Classes with linked records cannot be deleted.')">@csrf @method('DELETE')<button type="submit" class="admin-class-delete" aria-label="Delete {{ $name }}">Delete Class</button></form></div>
</article>
@empty
<p class="academic-empty">No classes added yet. Add your first class below.</p>
@endforelse
</div>
<p class="admin-classes-no-results" hidden role="status">No classes match your search.</p>
@else
<div class="student-table-scroll academic-setup-table"><table class="student-table"><caption class="sr-only">{{ $label }}</caption><thead><tr><th scope="col">#</th><th scope="col">{{ ucfirst($type) }} name</th>@if($type==='session')<th scope="col">Status</th>@endif</tr></thead><tbody>
@forelse($options[$field] as $id=>$name)<tr><td class="academic-row-number">{{ $loop->iteration }}</td><td><strong>{{ $name }}</strong></td>@if($type==='session')<td><span class="badge {{ (string)$currentSession===(string)$id ? 'badge-green' : 'badge-neutral' }}">{{ (string)$currentSession===(string)$id ? 'Current' : 'Other' }}</span></td>@endif</tr>@empty<tr><td colspan="{{ $type==='session' ? 3 : 2 }}" class="academic-empty">No {{ strtolower($label) }} added yet.</td></tr>@endforelse
</tbody></table></div>
@endif
<div class="student-form academic-add-form"><form method="POST" action="{{ route('academic-setup.store') }}">@csrf<input type="hidden" name="type" value="{{ $type }}"><div class="admission-field"><label for="name-{{ $type }}">New {{ $type }} name</label><input id="name-{{ $type }}" name="name" required maxlength="100" placeholder="{{ ['class'=>'e.g. Class 11','section'=>'e.g. Section D','session'=>'e.g. 2027-2028'][$type] }}" value="{{ old('type') === $type ? old('name') : '' }}"></div><div class="form-actions"><button class="button-primary">+ Add {{ ucfirst($type) }}</button></div></form></div>
</section>
@endforeach
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('admin-class-search');
    const cards = Array.from(document.querySelectorAll('[data-admin-class-name]'));
    const empty = document.querySelector('.admin-classes-no-results');
    input?.addEventListener('input', () => {
        const query = input.value.trim().toLocaleLowerCase();
        cards.forEach(card => { card.hidden = !card.dataset.adminClassName.includes(query); });
        empty.hidden = !cards.length || cards.some(card => !card.hidden);
    });
});
</script>
@endsection

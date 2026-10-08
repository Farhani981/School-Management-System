@extends('layouts.admin')
@section('title', 'Teacher Daily Routine')
@section('content')
@include('timetable.partials.header', ['heading' => 'Teacher Daily Routine', 'description' => 'Choose the class for each lecture once. The same routine runs every teaching day.'])
<form class="panel student-form" method="GET">
<div class="student-form-grid">
<div class="admission-field"><label for="routine-teacher">Teacher</label><select id="routine-teacher" name="teacher_id" onchange="this.form.requestSubmit()">@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($teacherId === $teacher->id)>{{ $teacher->name }}</option>@endforeach</select></div>
<div class="admission-field"><label for="routine-session">Session</label><select id="routine-session" name="session_id" onchange="this.form.requestSubmit()">@foreach($sessions as $session)<option value="{{ $session->id }}" @selected($sessionId === $session->id)>{{ $session->name }}</option>@endforeach</select></div>
</div></form>
@if($teacherId && $sessionId)
<form class="panel student-form" method="POST" action="{{ route('timetable.teacher-routine.store') }}">
@csrf<input type="hidden" name="teacher_id" value="{{ $teacherId }}"><input type="hidden" name="session_id" value="{{ $sessionId }}">
<p class="photo-help">Saving replaces this teacher's existing weekly schedule and publishes the daily routine. Leave a lecture free if no class is assigned. Conflicts with another teacher will block saving.</p>
<div class="admission-field"><label for="routine-days">Teaching days</label><select id="routine-days" name="teaching_days"><option value="6">Monday–Saturday</option><option value="7">Monday–Sunday (7 days)</option></select></div>
<div class="student-form-grid">
@foreach($periods as $period)
@if($period->is_break)<div class="admission-field"><label>{{ $period->name }}</label><p class="photo-help">{{ $period->formatted_time_range }} · Break</p></div>
@else
@php
    $entry = $entries->firstWhere('period_id', $period->id);
    $selected = $entry ? $assignments->first(fn ($assignment) => $assignment->class_id === $entry->class_id && $assignment->section_id === $entry->section_id && $assignment->subject_id === $entry->subject_id)?->id : null;
@endphp
<div class="admission-field"><label for="routine-period-{{ $period->id }}">{{ $period->name }} · {{ $period->formatted_time_range }}</label><select id="routine-period-{{ $period->id }}" name="periods[{{ $period->id }}]"><option value="">Free period</option>@foreach($assignments as $assignment)<option value="{{ $assignment->id }}" @selected(old('periods.'.$period->id, $selected) == $assignment->id)>{{ $assignment->schoolClass->name }} · {{ $assignment->section->name }} · {{ $assignment->subject->name }}</option>@endforeach</select></div>
@endif
@endforeach
</div><div class="form-actions"><button class="button-primary">Save Daily Routine for Whole Week</button></div>
</form>
@endif
@endsection

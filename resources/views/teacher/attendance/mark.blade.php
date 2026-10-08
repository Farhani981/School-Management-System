@extends('layouts.teacher')
@section('title','Mark Attendance')
@section('content')
@include('teacher.partials.header',['heading'=>$assignment->schoolClass->name.' · '.$assignment->section->name,'description'=>$assignment->subject->name.' · '.$assignment->session->name])
<form method="GET" class="panel student-form"><div class="student-form-grid"><div class="admission-field"><label for="mark-date">Attendance date</label><input id="mark-date" type="date" name="date" value="{{ $date }}" max="{{ today()->toDateString() }}" required></div></div><div class="form-actions"><button class="button-primary">Load Students</button><a class="button-secondary" href="{{ route('teacher.attendance.register',$assignment) }}">Monthly register</a></div></form>
@foreach($warnings as $warning)<div class="panel student-form" role="status">{{ $warning }}</div>@endforeach
@if($students->isNotEmpty())
<form method="POST" action="{{ route('teacher.attendance.store',$assignment) }}" class="panel attendance-mark-form">@csrf<input type="hidden" name="date" value="{{ $date }}">
<div class="panel-heading"><div><h2>{{ $students->count() }} active students</h2><p>{{ $marked->count() }} already marked. Save to update this day's attendance.</p></div><div class="student-actions"><button type="button" class="button-secondary" data-mark-all="present">Mark all Present</button><button type="button" class="button-secondary" data-mark-all="absent">Mark all Absent</button></div></div>
<div class="attendance-live-summary" data-attendance-summary aria-live="polite"></div>
@foreach($students as $student)
@php($index=$loop->index)
<div class="attendance-mark-row" data-attendance-row><div class="attendance-person">@include('students.partials.avatar')<div><strong>{{ $student->first_name }} {{ $student->last_name }}</strong><small class="student-secondary">Roll {{ $student->roll_number }} @if($marked->has($student->id)) · Already marked @endif</small></div></div><input type="hidden" name="rows[{{ $index }}][student_id]" value="{{ $student->id }}">
<div class="attendance-status-buttons" role="group" aria-label="Status for {{ $student->first_name }}">@foreach(['present','absent','late','leave'] as $status)<label><input type="radio" name="rows[{{ $index }}][status]" value="{{ $status }}" @checked(old('rows.'.$index.'.status',$marked->get($student->id)?->status ?? 'present')===$status) required><span>{{ ucfirst($status) }}</span></label>@endforeach</div>
<div class="admission-field"><label class="sr-only" for="remarks-{{ $student->id }}">Remarks</label><input id="remarks-{{ $student->id }}" name="rows[{{ $index }}][remarks]" value="{{ old('rows.'.$index.'.remarks',$marked->get($student->id)?->remarks) }}" maxlength="1000" placeholder="Remarks (optional)"></div></div>
@endforeach<div class="student-form"><div class="form-actions"><button class="button-primary">Save Attendance</button></div></div></form>
@else<div class="panel student-form">No active students in this class.</div>@endif
@endsection

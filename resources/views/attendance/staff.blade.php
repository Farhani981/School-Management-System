@extends('layouts.admin')
@section('title','Staff Attendance')
@section('content')
@include('attendance.partials.header',['heading'=>'Staff Attendance','description'=>'Approved leaves are filled automatically. Check-in determines lateness.'])
<form class="panel student-form" method="GET" action="{{ route('attendance.staff.index') }}"><div class="teacher-inline"><label for="staff-date">Date</label><input id="staff-date" name="date" type="date" value="{{ $date }}" max="{{ now()->toDateString() }}" required><button class="button-secondary">Load Staff</button></div></form>
<form class="panel student-form attendance-mark-form" method="POST" action="{{ route('attendance.staff.store') }}">@csrf<input type="hidden" name="date" value="{{ $date }}"><h2>Staff roster · {{ $marked->count() }} already marked</h2><p class="photo-help">Expected check-in: {{ config('attendance.staff_start_time') }}. Generated payroll months are locked.</p><div class="attendance-live-summary" data-attendance-summary aria-live="polite"></div>
@foreach($teachers as $teacher)
@php
$index=$loop->index;
$onLeave=$teacher->leaves->isNotEmpty();
$record=$marked->get($teacher->id);
@endphp
<div class="attendance-mark-row" data-attendance-row><div class="attendance-person">@include('teachers.partials.avatar')<div><strong>{{ $teacher->name }}</strong><small class="student-secondary">{{ $teacher->employee_id }} @if($onLeave) · Approved leave @endif @if($record) · Already marked @endif</small></div></div><input type="hidden" name="rows[{{ $index }}][teacher_id]" value="{{ $teacher->id }}">
@if($onLeave)<input type="hidden" name="rows[{{ $index }}][status]" value="leave"><span class="student-status badge-approved">Leave (automatic)</span>@else<div class="attendance-status-buttons" role="group" aria-label="Status for {{ $teacher->name }}">@foreach(\App\Models\StudentAttendance::STATUSES as $status)<label><input type="radio" name="rows[{{ $index }}][status]" value="{{ $status }}" @checked(old('rows.'.$index.'.status',$record?->status ?? 'present')===$status) required><span>{{ ucwords(str_replace('_',' ',$status)) }}</span></label>@endforeach</div>@endif
<div class="student-form-grid">@foreach(['check_in'=>'Check-in','check_out'=>'Check-out'] as $field=>$label)<div class="admission-field"><label for="{{ $field }}-{{ $teacher->id }}">{{ $label }}</label><input id="{{ $field }}-{{ $teacher->id }}" type="time" name="rows[{{ $index }}][{{ $field }}]" value="{{ old('rows.'.$index.'.'.$field, substr((string) optional($record)->getAttribute($field),0,5)) }}" @disabled($onLeave)></div>@endforeach<div class="admission-field"><label for="staff-remarks-{{ $teacher->id }}">Remarks</label><input id="staff-remarks-{{ $teacher->id }}" name="rows[{{ $index }}][remarks]" value="{{ old('rows.'.$index.'.remarks',$record?->remarks) }}" maxlength="1000"></div><div>Late: <strong>{{ $record?->late_minutes ?? 0 }} min</strong> (calculated on save)</div></div></div>@endforeach
<div class="form-actions"><button class="button-primary" @disabled($teachers->isEmpty())>Save Staff Attendance</button></div></form>
@endsection

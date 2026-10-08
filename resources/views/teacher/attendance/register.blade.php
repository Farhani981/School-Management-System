@extends('layouts.teacher')
@section('title','Monthly Register')
@section('content')
@include('teacher.partials.header',['heading'=>'Monthly Register','description'=>$assignment->schoolClass->name.' · '.$assignment->section->name.' · '.$assignment->session->name])
<form method="GET" class="panel student-form"><div class="admission-field"><label for="register-month">Month</label><input id="register-month" name="month" type="month" value="{{ $month }}" required></div><div class="form-actions"><button class="button-primary">View register</button>@can('mark', $assignment)<a class="button-secondary" href="{{ route('teacher.attendance.show',$assignment) }}">Mark attendance</a>@endcan</div></form>
<div class="panel student-form"><p>P = Present, A = Absent, L = Late, LV = Leave, H = Half day (existing records), — = Not marked.</p><div class="overflow-x-auto"><table class="student-table"><thead><tr><th>Roll</th><th>Student</th>@for($day=1;$day<=$start->daysInMonth;$day++)<th>{{ $day }}</th>@endfor</tr></thead><tbody>
@forelse($students as $student)<tr><td>{{ $student->roll_number }}</td><td><a href="{{ route('teacher.progress.index', ['student' => $student->id]) }}" class="font-bold text-ink hover-teal">{{ $student->first_name }} {{ $student->last_name }}</a></td>@php($days=$attendance->get($student->id,collect())->keyBy(fn($row)=>$row->date->day)) @for($day=1;$day<=$start->daysInMonth;$day++)<td>{{ ['present'=>'P','absent'=>'A','late'=>'L','leave'=>'LV','half_day'=>'H'][$days->get($day)?->status] ?? '—' }}</td>@endfor</tr>@empty<tr><td colspan="33">No active students in this class.</td></tr>@endforelse
</tbody></table></div>{{ $students->withQueryString()->links() }}</div>
@endsection

@extends('layouts.teacher')
@section('title','Class Students')
@section('content')
@include('teacher.partials.header',['heading'=>$assignment->schoolClass->name.' - Section '.$assignment->section->name,'description'=>$assignment->subject->name.' - '.$assignment->session->name])
<div class="panel student-form"><p>Read-only roster for your assigned class, section and academic session.</p><div class="overflow-x-auto"><table class="student-table"><thead><tr><th>Photo</th><th>Section</th><th>Roll no.</th><th>Name</th><th>Father name</th><th>Parent phone</th></tr></thead><tbody>
@forelse($students as $student)<tr><td>@include('students.partials.avatar')</td><td>{{ $student->section?->name ?? 'Unassigned' }}</td><td>{{ $student->roll_number }}</td><td>{{ $student->first_name }} {{ $student->last_name }}</td><td>{{ $student->father_name }}</td><td>@php($phone=$student->guardian_phone ?: $student->phone) @php($digits=preg_replace('/\D/','',$phone ?? '')) @php($whatsapp=str_starts_with($digits,'0') ? '92'.substr($digits,1) : $digits)
@if($digits){{ $phone }} <a href="tel:{{ $digits }}" class="button-secondary" aria-label="Call parent of {{ $student->first_name }}">Call</a> <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" class="button-secondary">WhatsApp</a>@else—@endif</td></tr>@empty<tr><td colspan="6">No active students in this class.</td></tr>@endforelse
</tbody></table></div>{{ $students->links() }}</div>
@endsection

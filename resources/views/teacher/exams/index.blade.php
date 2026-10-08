@extends('layouts.teacher')
@section('title','My Examinations')
@section('content')
<div class="exams-page">
    <div class="page-heading"><div><h1>My Examinations</h1><p>Enter marks and track submission status for your assigned examination papers.</p></div><a class="button-secondary" href="{{ route('teacher.exams.schedule') }}">Exam Schedule</a></div>
    <section class="panel">
        @include('teacher.exams.pending')
        <form method="GET" class="exam-toolbar"><div class="admission-field"><label for="paper-status">Marks status</label><select name="status" id="paper-status"><option value="">All statuses</option>@foreach(['not_started'=>'Marks pending','draft'=>'Draft','submitted'=>'Submitted','returned'=>'Returned','approved'=>'Approved'] as $status=>$label)<option value="{{ $status }}" @selected(($filters['status'] ?? '')===$status)>{{ $label }}</option>@endforeach</select></div><button class="button-secondary">Filter</button><a href="{{ url()->current() }}">Clear</a></form>
        <div class="student-table-scroll"><table class="student-table"><thead><tr><th>Examination / Subject</th><th>Class / Section</th><th>Date & Time</th><th>Room / Invigilator</th><th>Marks</th><th>Submission deadline</th><th>Status</th><th>Action</th></tr></thead><tbody>
            @forelse($papers as $paper)<tr><td>{{ $paper->examSubject->exam->name }}<small class="student-secondary">{{ $paper->examSubject->subject->name }} · {{ $paper->examSubject->exam->session->name }}</small></td><td>{{ $paper->examSubject->schoolClass->name }} / {{ $paper->section->name }}</td><td>{{ $paper->exam_date->format('d M Y') }}<small class="student-secondary">{{ $paper->time_label }}</small></td><td>{{ $paper->room ?? 'Not set' }}<small class="student-secondary">{{ $paper->invigilator?->user?->name ?? 'Not assigned' }}</small></td><td>Total {{ $paper->examSubject->total_marks }}<small class="student-secondary">Passing {{ $paper->examSubject->passing_marks }}</small></td><td>{{ $paper->marks_deadline?->format('d M Y, g:i A') ?? 'Not set' }}</td><td><span class="exam-status">{{ $paper->workflow_status }}</span>@if($paper->marks_status==='returned')<small class="student-secondary">{{ $paper->return_reason }}</small>@endif</td><td><a class="button-primary" href="{{ route('teacher.exams.marks.edit',$paper) }}">{{ in_array($paper->marks_status,['not_started','draft','returned']) && !$paper->examSubject->exam->result_published_at ? 'Enter Marks' : 'View Marks' }}</a></td></tr>
            @empty<tr><td colspan="8"><div class="exam-empty"><h2>No published papers {{ request('status') ? 'with this status' : 'assigned yet' }}</h2><p>Your papers appear once the administrator publishes the datesheet and confirms your subject assignment.</p></div></td></tr>@endforelse
        </tbody></table></div><div class="exam-pagination">{{ $papers->links() }}</div>
    </section>
</div>
@endsection

@extends('layouts.admin')
@section('title','Exam List')
@section('content')
<div class="exams-page exam-list-page">
<div class="page-heading"><div><h1>Exam List</h1><p>Configure marks, save paper dates, then publish to assigned teachers.</p></div><a class="button-primary" href="{{ route('exams.create') }}" aria-label="Create Examination">+ Add Exam</a></div>
@include('subjects.partials.feedback')
<section class="panel"><form method="GET" class="exam-list-toolbar">
<select name="session_id" aria-label="Academic session"><option value="">All Sessions</option>@foreach($sessions as $session)<option value="{{ $session->id }}" @selected(($filters['session_id'] ?? '')==$session->id)>{{ $session->name }}</option>@endforeach</select>
<input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search exams..." aria-label="Search exams"><button class="button-secondary">Search</button>
<a class="button-secondary" href="{{ route('exams.index',array_merge(request()->except('page'),['export'=>'csv'])) }}">Export CSV &darr;</a>
<label class="exam-page-size">Rows per page: <select name="per_page">@foreach([10,25,50] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 10)==$size)>{{ $size }}</option>@endforeach</select></label>
</form></section>
<div class="exam-overview-grid">
@forelse($exams as $exam)
@php
$setupErrors=app(\App\Services\ExamSetup::class)->checklist($exam);
$configuredClasses=$exam->classes->filter(fn($class)=>$exam->subjects->where('class_id',$class->id)->isNotEmpty())->count();
$expectedPapers=$exam->subjects->sum(fn($subject)=>$exam->participations->where('class_id',$subject->class_id)->count());
$published=(bool)$exam->datesheet_published_at;
$cancelled=$exam->status==='cancelled';
@endphp
<article class="exam-overview-card">
<div class="exam-overview-heading"><h2><a href="{{ route('exams.show',$exam) }}">{{ $exam->name }}</a></h2><span class="exam-status {{ $cancelled ? 'exam-status-cancelled' : '' }}">{{ $exam->status_label }}</span></div>
<span class="exam-overview-type">{{ \App\Models\Exam::TYPES[$exam->type] ?? ucfirst($exam->type) }}</span>
<div class="exam-overview-detail"><x-icon name="calendar" size="15"/><span>{{ $exam->start_date->format('d M') }} &ndash; {{ $exam->end_date->format('d M Y') }}</span></div>
@if($exam->start_time && $exam->end_time)<div class="exam-overview-detail"><x-icon name="clock" size="15"/><span>{{ \Carbon\Carbon::parse($exam->start_time)->format('h:i A') }} &ndash; {{ \Carbon\Carbon::parse($exam->end_time)->format('h:i A') }}</span></div>@endif
<div class="exam-overview-detail"><x-icon name="classes" size="15"/><span>{{ $exam->classes->pluck('name')->join(', ') }}</span></div>
<div class="exam-overview-detail"><x-icon name="clipboard" size="15"/><span>Marks Config: {{ $configuredClasses }}/{{ $exam->classes_count }} classes</span></div>
<div class="exam-overview-detail"><x-icon name="clock" size="15"/><span>Paper dates: {{ $exam->schedules_count }}/{{ $expectedPapers }}</span></div>
<small class="student-secondary">{{ $exam->schedules_count }} paper dates saved</small>
@if(!$cancelled)
<p class="exam-delivery-note">{{ $published ? 'Sent to selected teachers. Submitted marks appear in Student Results.' : 'Draft: open Create Datesheet, select subjects and teachers, then Send to Teachers.' }}</p>
@endif
<div class="exam-overview-actions"><a class="button-primary" href="{{ route('exams.show',$exam) }}">{{ $published ? 'View Datesheet' : 'Create Datesheet' }}</a><a class="button-secondary" href="{{ route('exams.results.index',$exam) }}">Student Results</a></div>
</article>
@empty
<div class="panel exam-empty"><h2>No examinations found</h2><p>Add an exam to get started.</p></div>
@endforelse
</div>
<div class="exam-pagination">{{ $exams->links() }}</div>
</div>
@endsection

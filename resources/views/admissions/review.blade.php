@extends('layouts.admin')
@section('title', 'Approve Admission')
@section('content')
<div class="page-heading"><div><h1>Approve {{ $application->applicant_name }}</h1><p>Complete the student details before approving application #{{ $application->id }}.</p></div><a class="button-secondary" href="{{ route('admissions.index') }}">Applications</a></div>
@include('students.partials.feedback')
@include('admissions.partials.tabs', ['activeTab' => 'applications'])
<section class="panel"><div class="panel-heading"><h2>Student Admission Details</h2></div><form method="POST" action="{{ route('admissions.applications.approve', $application) }}" enctype="multipart/form-data" class="student-form" data-confirm-action="Approve this application and create the student record?">@csrf @include('students.partials.form')<p class="photo-help">Choose a class, section and academic session. Approval creates one active student and assigns an admission number.</p><div class="form-actions"><a class="button-secondary" href="{{ route('admissions.index') }}">Cancel</a><button class="button-primary button-success">Approve &amp; Admit Student</button></div></form></section>
@endsection

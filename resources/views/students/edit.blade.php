@extends('layouts.admin')
@section('title', 'Edit Student')
@section('content')
<div class="page-heading"><div><div class="breadcrumb">Students <span>/</span> Edit</div><h1>Edit Student</h1><p>{{ $student->admission_no }} &middot; {{ $student->first_name }} {{ $student->last_name }}</p></div><a class="button-secondary" href="{{ route('students.index') }}">Back to Students</a></div>
@include('students.partials.feedback')
<section class="panel"><div class="panel-heading"><h2>Student details</h2><span class="muted">* Required fields</span></div><form action="{{ route('students.update', $student) }}" method="POST" enctype="multipart/form-data" class="student-form">@csrf @method('PUT') @include('students.partials.form')<div class="form-actions"><a class="button-secondary" href="{{ route('students.show', $student) }}">Cancel</a><button class="button-primary" type="submit">Save Changes</button></div></form></section>
@if($feeData)<div id="fees">@include('fees.partials.student')</div>@endif
@endsection


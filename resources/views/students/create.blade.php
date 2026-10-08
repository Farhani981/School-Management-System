@extends('layouts.admin')
@section('title', 'Add Student')
@section('content')
<div class="page-heading"><div><div class="breadcrumb">Students <span>/</span> Admission</div><h1>Add Student</h1><p>Create a new student record.</p></div><a class="button-secondary" href="{{ route('students.index') }}">Back to Students</a></div>
@include('students.partials.feedback')
@include('admissions.partials.tabs', ['activeTab' => 'new'])
<div class="admission-form-intro"><span>New Admission</span><span class="muted"><span class="required-mark">*</span> Required fields</span></div>
<form action="{{ route('admissions.students.store') }}" method="POST" enctype="multipart/form-data" class="student-form admission-design">@csrf @include('students.partials.form', ['admissionDesign' => true])<div class="form-actions admission-save-actions"><a class="button-secondary" href="{{ route('admissions.index') }}">Cancel</a><button class="button-primary" type="submit">Admit Student</button></div></form>
@endsection


@extends('layouts.admin')
@section('title', 'Edit Class')
@section('content')
<div class="page-heading"><div><div class="breadcrumb">Academics <span>/</span> Classes</div><h1>Edit Class</h1><p>Update the class name across student records, reports and academic screens.</p></div><a class="button-secondary" href="{{ route('academic-setup.index') }}">Back to Classes</a></div>
@include('students.partials.feedback')
<form method="POST" action="{{ route('academic-setup.classes.update', $schoolClass) }}" class="panel student-form admin-class-edit-form">
    @csrf @method('PUT')
    <div class="admission-field"><label for="class-name">Class name <span class="required-mark">*</span></label><input id="class-name" name="name" value="{{ old('name', $schoolClass->name) }}" maxlength="100" required autofocus>@error('name')<span class="field-error">{{ $message }}</span>@enderror</div>
    <div class="form-actions"><a class="button-secondary" href="{{ route('academic-setup.index') }}">Cancel</a><button type="submit" class="button-primary">Save Changes</button></div>
</form>
@endsection

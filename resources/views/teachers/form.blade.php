@extends('layouts.admin')
@section('title', $teacher->exists ? 'Edit Teacher' : 'Add Teacher')
@section('content')
<div class="page-heading"><div><h1>{{ $teacher->exists ? 'Edit Teacher' : 'Add Teacher' }}</h1><p>{{ $teacher->employee_id ?: 'Employee ID is assigned automatically when saved.' }}</p></div><a class="button-secondary" href="{{ route('teachers.index') }}">Back to Teachers</a></div>
@include('students.partials.feedback')
@if($departments->isEmpty() || $designations->isEmpty())<p class="student-feedback">Use “Add new department” and “Add new designation” in Employment to create your options before saving.</p>@endif
<form class="panel student-form" method="POST" enctype="multipart/form-data" action="{{ $teacher->exists ? route('teachers.update',$teacher) : route('teachers.store') }}">@csrf @if($teacher->exists) @method('PUT') @endif
@php
$groups = ['Personal'=>['name'=>'Name','cnic'=>'CNIC','gender'=>'Gender','date_of_birth'=>'Date of Birth'],'Contact'=>['phone'=>'Phone','email'=>'Email','address'=>'Address','emergency_contact'=>'Emergency Contact'],'Employment'=>['employee_id'=>'Employee ID','department_id'=>'Department','designation_id'=>'Designation','joining_date'=>'Joining Date','status'=>'Status'],'Qualification'=>['qualification'=>'Qualifications','experience'=>'Experience']];
@endphp
@foreach($groups as $group=>$fields)<fieldset class="admission-section"><legend>{{ $group }}</legend><div class="student-form-grid">
@foreach($fields as $field=>$label)
@php
$value = old($field, in_array($field,['basic_salary','allowances','deductions']) ? ($salary?->$field ?? 0) : (in_array($field,['joining_date','date_of_birth']) ? $teacher->$field?->format('Y-m-d') : $teacher->$field));
$required = in_array($field,['name','cnic','gender','phone','department_id','designation_id','joining_date','status','basic_salary','allowances','deductions']);
$choices = match($field) { 'department_id'=>$departments->pluck('name','id'), 'designation_id'=>$designations->pluck('name','id'), 'gender'=>collect(['Male'=>'Male','Female'=>'Female','Other'=>'Other']), 'status'=>collect(['Active'=>'Active','Inactive'=>'Inactive','On Leave'=>'On Leave']), default=>null };
@endphp
<div class="admission-field"><label for="teacher-{{ $field }}">{{ $label }} @if($required)<span class="required-mark">*</span>@endif</label>
@if($field==='employee_id')<input id="teacher-employee_id" readonly value="{{ $teacher->employee_id ?: 'EMP-'.now()->year.'-001 (automatic)' }}">
@elseif($choices)<select id="teacher-{{ $field }}" name="{{ $field }}" required><option value="">Select {{ $label }}</option>@foreach($choices as $id=>$name)<option value="{{ $id }}" @selected((string)($value ?: ($field==='status'?'Active':''))===(string)$id)>{{ $name }}</option>@endforeach</select>
@if(in_array($field, ['department_id', 'designation_id']))
<details class="academic-quick-add" data-target="teacher-{{ $field }}" data-type="{{ $field === 'department_id' ? 'department' : 'designation' }}" data-url="{{ route('teachers.setup') }}" @if($choices->isEmpty()) open @endif>
    <summary>Add new {{ strtolower($label) }}</summary>
    <label for="new-teacher-{{ $field }}">New {{ strtolower($label) }} name</label>
    <input id="new-teacher-{{ $field }}" type="text" maxlength="100" placeholder="{{ $field === 'department_id' ? 'e.g. Science, Mathematics, English' : 'e.g. Lecturer, Senior Teacher' }}">
    <button type="button" class="button-secondary" data-save-option>Add {{ $label }}</button>
    <p role="status" data-option-message></p>
</details>
@if($choices->isEmpty())<small data-empty-options="teacher-{{ $field }}">No {{ strtolower($label) }} options yet. Add one above; it will be selected automatically.</small>@endif
@endif
@elseif(in_array($field,['address','qualification','experience']))<textarea id="teacher-{{ $field }}" name="{{ $field }}" rows="3">{{ $value }}</textarea>
@else<input id="teacher-{{ $field }}" name="{{ $field }}" value="{{ $value ?? ($field==='joining_date'?now()->format('Y-m-d'):'') }}" type="{{ in_array($field,['basic_salary','allowances','deductions'])?'number':(in_array($field,['joining_date','date_of_birth'])?'date':($field==='email'?'email':($field==='phone'?'tel':'text'))) }}" @required($required) @if(in_array($field,['basic_salary','allowances','deductions'])) min="0" step="0.01" @endif @if($field==='cnic') pattern="\d{5}-\d{7}-\d" placeholder="12345-1234567-1" @endif>
@endif
@error($field)<span class="field-error">{{ $message }}</span>@enderror</div>
@endforeach</div></fieldset>@endforeach
@include('teachers.partials.salary')
<fieldset class="admission-section"><legend>Login account</legend><p class="photo-help">Creates a user with role Teacher. Existing accounts are updated; leave the password empty to keep it.</p><label><input type="checkbox" name="create_login" value="1" @checked(old('create_login',$teacher->user_id))> Create / maintain Teacher account</label><div class="student-form-grid">
<div class="admission-field"><label for="login-email">Login email</label><input id="login-email" name="login_email" type="email" value="{{ old('login_email',$teacher->user?->email) }}"></div><div class="admission-field"><label for="login-password">Password</label><input id="login-password" name="password" type="password" minlength="8" autocomplete="new-password"></div><div class="admission-field"><label for="login-confirmation">Confirm password</label><input id="login-confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password"></div></div></fieldset>
<fieldset class="admission-section"><legend>Photo</legend>@include('teachers.partials.avatar')<div class="admission-field"><label for="teacher-photo">Upload photo</label><input id="teacher-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"><p class="photo-help">JPG, PNG or WebP, up to 5 MB. Initials appear when no photo is available.</p></div></fieldset>
<fieldset class="admission-section"><legend>Documents</legend><div class="admission-field"><label for="teacher-documents">Certificates, CNIC and other documents</label><input id="teacher-documents" type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png"><p class="photo-help">Up to 10 PDF, JPG or PNG files, 5 MB each. Existing documents remain available on the profile.</p></div></fieldset>
<div class="form-actions"><a class="button-secondary" href="{{ route('teachers.index') }}">Cancel</a><button class="button-primary">Save Teacher</button></div></form>
@include('teachers.partials.setup')
@endsection

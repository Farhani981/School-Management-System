@extends('layouts.admin')
@section('title',$subject->exists ? 'Edit Subject' : 'Add Subject')
@section('content')
<div class="page-heading"><div><h1>{{ $subject->exists ? 'Edit Subject' : 'Add Subject' }}</h1><p>Keep your subject details and codes consistent.</p></div><a class="button-secondary" href="{{ route('subjects.index') }}">Back to Subjects</a></div>
@include('subjects.partials.feedback')
@include('subjects.partials.tabs')
<form class="panel student-form" method="POST" action="{{ $subject->exists ? route('subjects.update',$subject) : route('subjects.store') }}">@csrf @if($subject->exists) @method('PUT') @endif
<fieldset class="admission-section"><legend>Subject Details</legend><div class="student-form-grid">
@foreach(['name'=>'Subject Name','code'=>'Subject Code'] as $field=>$label)<div class="admission-field"><label for="subject-{{ $field }}">{{ $label }} <span class="required-mark">*</span></label><input id="subject-{{ $field }}" name="{{ $field }}" value="{{ old($field,$subject->$field) }}" maxlength="{{ $field==='code'?50:255 }}" required @if($field==='code') placeholder="e.g. ENG" pattern="[A-Za-z0-9_-]+" @endif>@error($field)<span class="field-error">{{ $message }}</span>@enderror</div>@endforeach
@foreach(['type'=>['theory','practical','both'],'category'=>['compulsory','optional','elective'],'status'=>['Active','Inactive']] as $field=>$options)<div class="admission-field"><label for="subject-{{ $field }}">{{ ucfirst($field) }} <span class="required-mark">*</span></label><select id="subject-{{ $field }}" name="{{ $field }}" required>@foreach($options as $option)<option value="{{ $option }}" @selected(old($field,$subject->$field)===$option)>{{ ucfirst($option) }}</option>@endforeach</select>@error($field)<span class="field-error">{{ $message }}</span>@enderror</div>@endforeach
<div class="admission-field student-address"><label for="subject-description">Description</label><textarea id="subject-description" name="description" rows="4" maxlength="5000">{{ old('description',$subject->description) }}</textarea></div></div></fieldset>
<div class="form-actions"><a class="button-secondary" href="{{ route('subjects.index') }}">Cancel</a><button class="button-primary">Save Subject</button></div></form>
@endsection

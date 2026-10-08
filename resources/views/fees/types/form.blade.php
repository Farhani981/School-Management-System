@extends('layouts.admin')
@section('title',$type->exists?'Edit Fee Type':'Add Fee Type')
@section('content')
@include('fees.partials.header',['title'=>$type->exists?'Edit Fee Type':'Add Fee Type'])
<form class="panel student-form" method="POST" action="{{ $type->exists?route('fees.setup.types.update',$type):route('fees.setup.types.store') }}">@csrf @if($type->exists) @method('PUT') @endif
<fieldset class="admission-section"><legend>Fee type details</legend><div class="student-form-grid">
<div class="admission-field"><label for="name">Name *</label><input id="name" name="name" required maxlength="100" value="{{ old('name',$type->name) }}"></div>
<div class="admission-field"><label for="frequency">Frequency *</label><select id="frequency" name="frequency" required>@foreach(\App\Models\FeeType::FREQUENCIES as $value=>$label)<option value="{{ $value }}" @selected(old('frequency',$type->frequency??'monthly')===$value)>{{ $label }}</option>@endforeach</select></div>
<div class="admission-field"><label for="is-active">Status</label><input type="hidden" name="is_active" value="0"><label><input id="is-active" class="fee-checkbox" type="checkbox" name="is_active" value="1" @checked(old('is_active',$type->exists?$type->is_active:true))> Active</label></div>
</div></fieldset><div class="form-actions"><button class="button-primary">Save Fee Type</button><a class="button-secondary" href="{{ route('fees.setup.types.index') }}">Cancel</a></div></form>
@endsection

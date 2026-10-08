@extends('layouts.admin')
@section('title',$discount->exists?'Edit Discount':'Add Discount')
@section('content')
@include('fees.partials.header',['title'=>$discount->exists?'Edit Discount':'Add Discount'])
<form class="panel student-form" method="POST" action="{{ $discount->exists?route('fees.setup.discounts.update',$discount):route('fees.setup.discounts.store') }}">@csrf @if($discount->exists) @method('PUT') @endif
<fieldset class="admission-section"><legend>Discount details</legend><div class="student-form-grid">
<div class="admission-field"><label for="name">Name *</label><input id="name" name="name" required maxlength="100" value="{{ old('name',$discount->name) }}"></div>
<div class="admission-field"><label for="type">Type *</label><select id="type" name="type"><option value="fixed" @selected(old('type',$discount->type)==='fixed')>Fixed amount (Rs.)</option><option value="percent" @selected(old('type',$discount->type)==='percent')>Percentage (%)</option></select></div>
<div class="admission-field"><label for="value">Value *</label><input id="value" name="value" type="number" min="0" step="0.01" max="99999999.99" required value="{{ old('value',$discount->value??0) }}"><small>Percentage cannot exceed 100.</small></div>
<div class="admission-field"><label>Status</label><input type="hidden" name="is_active" value="0"><label><input name="is_active" class="fee-checkbox" type="checkbox" value="1" @checked(old('is_active',$discount->exists?$discount->is_active:true))> Active</label></div>
</div></fieldset><div class="form-actions"><button class="button-primary">Save Discount</button><a class="button-secondary" href="{{ route('fees.setup.discounts.index') }}">Cancel</a></div></form>
@endsection

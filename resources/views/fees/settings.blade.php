@extends('layouts.admin')
@section('title','Fee Settings')
@section('content')
@include('fees.partials.header',['title'=>'Fee Settings'])
<form class="panel student-form" method="POST" action="{{ route('fees.setup.settings.update') }}">@csrf @method('PUT')
<fieldset class="admission-section"><legend>Due date and late fine rules</legend><div class="student-form-grid">
<div class="admission-field"><label for="due-day">Due day *</label><input id="due-day" type="number" name="due_day" required min="1" max="31" value="{{ old('due_day',$settings->due_day) }}"><small>Days 29–31 use the month's final day when necessary.</small></div>
<div class="admission-field"><label for="fine-type">Fine type</label><select id="fine-type" name="fine_type">@foreach(['none'=>'None','fixed'=>'Fixed','per_day'=>'Per day'] as $value=>$label)<option value="{{ $value }}" @selected(old('fine_type',$settings->fine_type)===$value)>{{ $label }}</option>@endforeach</select></div>
@foreach(['fine_amount'=>'Fine amount', 'fine_max_cap'=>'Maximum fine cap'] as $field=>$label)<div class="admission-field"><label for="{{ $field }}">{{ $label }}</label><div class="salary-amount"><span>Rs.</span><input id="{{ $field }}" type="number" name="{{ $field }}" min="0" max="99999999.99" step="0.01" value="{{ old($field,$settings->$field) }}"></div></div>@endforeach
<div class="admission-field"><label for="grace-days">Grace days *</label><input id="grace-days" type="number" name="grace_days" required min="0" max="365" value="{{ old('grace_days',$settings->grace_days) }}"></div>
</div><p class="photo-help">Late fine settings are saved for Phase 2. Current fee previews always show fine and arrears as Rs. 0. A zero maximum cap means no cap.</p></fieldset>
<fieldset class="admission-section"><legend>Number prefixes</legend><div class="student-form-grid">@foreach(['voucher_prefix'=>'Voucher prefix','receipt_prefix'=>'Receipt prefix'] as $field=>$label)<div class="admission-field"><label for="{{ $field }}">{{ $label }} *</label><input id="{{ $field }}" name="{{ $field }}" required maxlength="10" value="{{ old($field,$settings->$field) }}"><small>Letters, numbers and hyphens; starts with a letter.</small></div>@endforeach</div></fieldset>
<fieldset class="admission-section"><legend>Sibling discount rule</legend><div class="student-form-grid">
<div class="admission-field"><input type="hidden" name="sibling_discount_enabled" value="0"><label><input type="checkbox" class="fee-checkbox" name="sibling_discount_enabled" value="1" @checked(old('sibling_discount_enabled',$settings->sibling_discount_enabled))> Enable sibling discount</label></div>
<div class="admission-field"><label for="sibling-type">Type</label><select id="sibling-type" name="sibling_discount_type"><option value="percent" @selected(old('sibling_discount_type',$settings->sibling_discount_type)==='percent')>Percentage (%)</option><option value="fixed" @selected(old('sibling_discount_type',$settings->sibling_discount_type)==='fixed')>Fixed amount (Rs.)</option></select></div>
<div class="admission-field"><label for="sibling-value">Value</label><input id="sibling-value" type="number" name="sibling_discount_value" min="0" max="99999999.99" step="0.01" value="{{ old('sibling_discount_value',$settings->sibling_discount_value) }}"><small>Percentage cannot exceed 100.</small></div>
</div><p class="photo-help">Applied from the second active child onward in the same session, ordered by admission date then student ID. Families match by Family ID or father name and phone. The rule applies after other discounts.</p></fieldset>
<div class="form-actions"><button class="button-primary">Save Fee Settings</button></div></form>
@endsection

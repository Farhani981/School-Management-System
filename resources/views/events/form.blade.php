@extends('layouts.admin')
@section('title', $event->exists ? 'Edit Event' : 'Add Event')
@section('content')
<div class="page-heading"><div><h1>{{ $event->exists ? 'Edit Event' : 'Add Event' }}</h1><p>These dates appear on the Dashboard calendar.</p></div><a class="button-secondary" href="{{ route('events.index') }}">Back</a></div>
@include('students.partials.feedback')
<form class="panel student-form" method="POST" action="{{ $event->exists ? route('events.update', $event) : route('events.store') }}">@csrf @if($event->exists) @method('PUT') @endif
<fieldset class="admission-section"><legend>Event details</legend><div class="student-form-grid">
<div class="admission-field"><label for="title">Title *</label><input id="title" name="title" required maxlength="255" value="{{ old('title', $event->title) }}"></div>
@foreach(['date'=>'Start date', 'end_date'=>'End date'] as $field=>$label)<div class="admission-field"><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="date" value="{{ old($field, $event->$field?->format('Y-m-d')) }}" @required($field==='date')></div>@endforeach
@foreach(['type'=>['holiday','meeting','exam','event'], 'color'=>['blue','green','coral','pink','amber']] as $field=>$choices)<div class="admission-field"><label for="{{ $field }}">{{ ucfirst($field) }}</label><select id="{{ $field }}" name="{{ $field }}" required>@foreach($choices as $choice)<option value="{{ $choice }}" @selected(old($field, $event->$field ?? ($field==='type'?'event':'blue'))===$choice)>{{ ucfirst($choice) }}</option>@endforeach</select></div>@endforeach
<div class="admission-field student-address"><label for="description">Description</label><textarea id="description" name="description" rows="3" maxlength="3000">{{ old('description', $event->description) }}</textarea></div>
</div></fieldset><div class="form-actions"><button class="button-primary">Save Event</button><a class="button-secondary" href="{{ route('events.index') }}">Cancel</a></div></form>
@endsection

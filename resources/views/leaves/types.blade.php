@extends('layouts.admin')
@section('title', 'Leave Types')
@section('content')
<div class="leaves-page">
<h1>Leave Types</h1><p>Configure entitlement and application rules. Zero quota or unpaid types have no balance limit. Existing session totals require a separate adjustment.</p>
@include('leaves.messages')
<section class="leave-card"><h2>Add leave type</h2><form method="POST" action="{{ route('leaves.types.store') }}">@csrf @include('leaves.type-fields', ['type' => null, 'prefix' => 'new'])<button type="submit">Add type</button></form></section>
@foreach($types as $type)<section class="leave-card"><h2>{{ $type->name }} <span class="leave-pill">{{ $type->is_active ? 'Active' : 'Inactive' }}</span></h2><p>{{ $type->requests_count }} requests. Deactivate to stop new applications; types are never deleted.</p><form method="POST" action="{{ route('leaves.types.update', $type) }}">@csrf @method('PUT') @include('leaves.type-fields', ['prefix' => 'type-'.$type->id])<button type="submit">Save {{ $type->name }}</button></form></section>@endforeach
</div>
@endsection

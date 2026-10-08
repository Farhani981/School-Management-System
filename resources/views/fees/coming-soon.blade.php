@extends('layouts.admin')
@section('title',$module)
@section('content')
<div class="page-heading"><div><div class="breadcrumb">Fees <span>/</span> {{ $module }}</div><h1>{{ $module }}</h1><p>Coming soon</p></div><a class="button-secondary" href="{{ route('fees.setup.index') }}">Fee Setup</a></div>
<section class="panel"><div class="students-empty"><span><x-icon name="wallet" size="30" /></span><h3>Coming soon</h3><p>{{ $module }} will be available in a later phase. You can configure fees now.</p><a class="button-primary" href="{{ route('fees.setup.index') }}">Open Fee Setup</a></div></section>
@endsection

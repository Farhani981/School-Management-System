@extends('layouts.portal')
@section('title', 'Library')

@section('content')
<div class="portal-page-header">
    <div>
        <h1 class="portal-page-title">Library</h1>
        <p class="portal-page-subtitle">Browse catalog books, issued titles, and due dates</p>
    </div>
</div>

<x-portal.empty-state
    title="Library Management Coming Soon"
    description="Search through the school book repository, track active loans, and view return deadlines directly from your account."
    icon="classes"
    badge="Coming Soon"
/>
@endsection

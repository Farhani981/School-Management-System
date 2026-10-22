@extends('layouts.portal')
@section('title', 'Class Timetable')

@section('content')
<div class="portal-page-header">
    <div>
        <h1 class="portal-page-title">Class Timetable</h1>
        <p class="portal-page-subtitle">Weekly period schedule, subject timings, and room allocations</p>
    </div>
</div>

<x-portal.empty-state
    title="Timetable View Coming Soon"
    description="Your weekly routine schedule with class periods, subject teachers, and room numbers will be shown in an interactive grid."
    icon="clock"
    badge="Coming Soon"
/>
@endsection

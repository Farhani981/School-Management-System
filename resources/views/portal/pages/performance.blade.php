@extends('layouts.portal')
@section('title', 'My Performance')

@section('content')
<div class="portal-page-header">
    <div>
        <h1 class="portal-page-title">My Performance</h1>
        <p class="portal-page-subtitle">Academic growth charts, subject-wise trends, and teacher feedback</p>
    </div>
</div>

<x-portal.empty-state
    title="Performance Analytics Coming Soon"
    description="Subject-by-subject assessment graphs, cumulative GPA trackers, and personalized study recommendations will be available here."
    icon="chart"
    badge="Coming Soon"
/>
@endsection

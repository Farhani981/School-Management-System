@extends('layouts.portal')
@section('title', 'Online Quizzes')

@section('content')
<div class="portal-page-header">
    <div>
        <h1 class="portal-page-title">Online Quizzes</h1>
        <p class="portal-page-subtitle">Participate in scheduled assessments, tests, and mock quizzes</p>
    </div>
</div>

<x-portal.empty-state
    title="Online Quizzes Coming Soon"
    description="Interactive quizzes created by your subject teachers with instant scoring and review sheets will be ready soon."
    icon="clipboard"
    badge="Coming Soon"
/>
@endsection

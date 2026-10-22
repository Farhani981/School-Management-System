@extends('layouts.portal')
@section('title', 'Study Material & Notes')

@section('content')
<div class="portal-page-header">
    <div>
        <h1 class="portal-page-title">Study Material & Notes</h1>
        <p class="portal-page-subtitle">Download syllabus guides, presentations, and subject worksheets</p>
    </div>
</div>

<x-portal.empty-state
    title="Study Material Repository Coming Soon"
    description="Subject notes uploaded by your teachers, downloadable syllabus documents, and assignment guidelines will appear here."
    icon="book"
    badge="Coming Soon"
/>
@endsection

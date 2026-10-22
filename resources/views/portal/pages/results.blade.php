@extends('layouts.portal')
@section('title', 'Exam Results & Report Cards')

@section('content')
<div class="portal-page-header">
    <div>
        <h1 class="portal-page-title">Exam Results & Report Cards</h1>
        <p class="portal-page-subtitle">View official term exam scores, grades, and printable report cards</p>
    </div>
</div>

<x-portal.empty-state
    title="Results Portal Coming Soon"
    description="Term examination transcripts, printable PDF report cards, and grading rubrics will be published here directly from the examination cell."
    icon="award"
    badge="Coming Soon"
/>
@endsection

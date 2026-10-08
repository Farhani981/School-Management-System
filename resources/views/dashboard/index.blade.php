@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
    <div class="page-heading dashboard-welcome">
        <div>
            <div class="breadcrumb">Workspace <span>/</span> Overview</div>
            <h1>Dashboard</h1>
            <p>A clear view of your school, all in one place.</p>
        </div>
        <div class="heading-date"><x-icon name="calendar" size="15" /><time
                datetime="{{ $today->toDateString() }}">{{ $today->format('D, d M Y') }}</time></div>
    </div>
    @include('dashboard.partials.statistics')
    @include('dashboard.partials.reports')
    <div class="dashboard-grid grid grid-cols-1 gap-4 lg:grid-cols-2">
        @include('dashboard.partials.calendar')
        @include('dashboard.partials.attendance')
        @include('dashboard.partials.fees')
        @include('dashboard.partials.activities')
    </div>
    <section class="panel dashboard-quick-actions" aria-labelledby="quick-actions-title">
        <div class="panel-heading"><div><h2 id="quick-actions-title">Quick Actions</h2><p>Frequently used school tasks</p></div></div>
        <div class="dashboard-quick-links">
            @foreach(['students.create' => ['Add Student','users'], 'teachers.create' => ['Add Teacher','teacher'], 'academic-setup.index' => ['Create Class','classes'], 'fees.collection.index' => ['Collect Fee','wallet'], 'exams.create' => ['Create Exam','calendar']] as $route => [$label, $icon])
                <a class="button-secondary" href="{{ route($route) }}"><x-icon :name="$icon" size="16" />{{ $label }}</a>
            @endforeach
        </div>
    </section>
@endsection

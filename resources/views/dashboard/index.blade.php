@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
    <div class="page-heading dashboard-welcome">
        <div class="dashboard-welcome-bg-art">
            <img src="{{ asset('images/dashboard-welcome-bg.jpg') }}" alt="School illustration" />
        </div>
        <div class="dashboard-welcome-copy">
            <span class="dashboard-welcome-mark">
                <x-icon name="school" size="30" />
            </span>
            <div>
                <h1>Welcome Back, School Admin!</h1>
                <p>Here's what's happening at your school today.</p>
            </div>
        </div>
        <div class="heading-date">
            <x-icon name="calendar" size="14" />
            <time datetime="{{ $today->toDateString() }}">{{ $today->format('D, d M Y') }}</time>
            <x-icon name="chevron" size="12" />
        </div>
    </div>

    @include('dashboard.partials.statistics')

    @include('dashboard.partials.reports')

    <div class="dashboard-main-grid">
        <div class="dashboard-column dashboard-col-left">
            @include('dashboard.partials.calendar')
            @include('dashboard.partials.fees')
        </div>
        <div class="dashboard-column dashboard-col-right">
            @include('dashboard.partials.attendance')
            @include('dashboard.partials.activities')
        </div>
    </div>

    <section class="panel dashboard-quick-actions" aria-labelledby="quick-actions-title">
        <div class="panel-heading">
            <span class="panel-icon panel-icon-quick"><x-icon name="arrow" size="16" /></span>
            <div>
                <h2 id="quick-actions-title">Quick Actions</h2>
                <p>Frequently used school tasks.</p>
            </div>
        </div>
        <div class="dashboard-quick-links">
            <a class="quick-action-btn quick-action-student" href="{{ route('students.create') }}">
                <x-icon name="users" size="15" />
                <span>+ Add Student</span>
            </a>
            <a class="quick-action-btn quick-action-teacher" href="{{ route('teachers.create') }}">
                <x-icon name="teacher" size="15" />
                <span>+ Add Teacher</span>
            </a>
            <a class="quick-action-btn quick-action-class" href="{{ route('academic-setup.index') }}">
                <x-icon name="classes" size="15" />
                <span>+ Create Class</span>
            </a>
            <a class="quick-action-btn quick-action-fee" href="{{ route('fees.collection.index') }}">
                <x-icon name="wallet" size="15" />
                <span>Collect Fee</span>
            </a>
            <a class="quick-action-btn quick-action-exam" href="{{ route('exams.create') }}">
                <x-icon name="clipboard" size="15" />
                <span>Create Exam</span>
            </a>
        </div>
    </section>
@endsection

@extends('layouts.teacher')
@section('title', 'Student Progress')

@section('content')
<div class="progress-module" data-progress-module>
    <!-- Page Header & Action Bar -->
    <div class="quizzes-header-bar">
        <div>
            <h1 class="quizzes-title">Student Progress</h1>
            <p class="quizzes-subtitle">Track how each student is doing.</p>
        </div>

        <!-- Section / Class Selector -->
        <div class="progress-top-controls">
            <form method="GET" action="{{ route('teacher.progress.index') }}" class="progress-section-selector" id="section-select-form">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                @if($selectedStudent && $activeTab === 'student')
                    <input type="hidden" name="student" value="{{ $selectedStudent->id }}">
                @endif
                <div class="selector-field">
                    <label for="select-class-section" class="sr-only">Assigned Section</label>
                    <select id="select-class-section" name="class_section" onchange="const [c, s] = this.value.split('-'); window.location.href = '{{ route('teacher.progress.index') }}?class_id=' + c + '&section_id=' + s + '&tab={{ $activeTab }}';">
                        @foreach($assignedSections as $sec)
                            <option value="{{ $sec['class_id'] }}-{{ $sec['section_id'] }}" @selected($sec['class_id'] === $selectedClassId && $sec['section_id'] === $selectedSectionId)>
                                {{ $sec['display_name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            @if($activeTab === 'class' && $classData)
                <a href="{{ route('teacher.progress.export.class', ['class_id' => $selectedClassId, 'section_id' => $selectedSectionId, 'session_id' => $sessionId]) }}" class="button-secondary btn-export-csv" title="Download class CSV report">
                    <x-icon name="clipboard" size="15" /> Export Class CSV
                </a>
            @endif
        </div>
    </div>

    @if(empty($assignedSections))
        <div class="quizzes-table-card text-center p-6">
            <x-icon name="users" size="36" class="text-mute mx-auto mb-3" />
            <h2 class="text-base font-bold text-ink">No Classes Assigned</h2>
            <p class="text-sm text-mute">You do not have any teaching sections assigned for the current academic session.</p>
        </div>
    @else
        <!-- Main Navigation Tabs -->
        <nav class="progress-tabs-nav" aria-label="Progress view mode">
            <a href="{{ route('teacher.progress.index', ['class_id' => $selectedClassId, 'section_id' => $selectedSectionId, 'tab' => 'student', 'student' => $selectedStudent?->id]) }}"
               class="progress-tab-link @if($activeTab === 'student') active @endif">
                <x-icon name="teacher" size="16" />
                <span>Student</span>
                @if($selectedStudent)
                    <span class="tab-chip">{{ $selectedStudent->first_name }}</span>
                @endif
            </a>
            <a href="{{ route('teacher.progress.index', ['class_id' => $selectedClassId, 'section_id' => $selectedSectionId, 'tab' => 'class', 'filter' => $filterMode]) }}"
               class="progress-tab-link @if($activeTab === 'class') active @endif">
                <x-icon name="chart" size="16" />
                <span>Class Overview</span>
                @if(!empty($classData['needs_attention']))
                    <span class="tab-warn-badge" title="{{ count($classData['needs_attention']) }} students need attention">{{ count($classData['needs_attention']) }}</span>
                @endif
            </a>
        </nav>

        <!-- View Content -->
        @if($activeTab === 'student')
            @include('teacher.progress.partials.student-view')
        @else
            @include('teacher.progress.partials.class-overview')
        @endif
    @endif
</div>
@endsection

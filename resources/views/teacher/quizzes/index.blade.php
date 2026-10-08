@extends('layouts.teacher')
@section('title', 'Quizzes & Results')

@section('content')
<div class="quizzes-module">
    <!-- Page Header & Action Bar -->
    <div class="quizzes-header-bar">
        <div>
            <h1 class="quizzes-title">Quizzes & Results</h1>
            <p class="quizzes-subtitle">Create offline assessments, enter student marks, track results, and review academic performance.</p>
        </div>
        <div class="quizzes-header-actions">
            @if(isset($pendingCount) && $pendingCount > 0)
                <span class="badge badge-warn" title="{{ $pendingCount }} published assessments are waiting for student marks">
                    <span class="my-status-dot dot-warn"></span> {{ $pendingCount }} {{ \Illuminate\Support\Str::plural('assessment', $pendingCount) }} pending marks
                </span>
            @endif
            <a href="{{ route('teacher.quizzes.create') }}" class="button-primary btn-add-quiz">
                <span aria-hidden="true">+</span> Create Quiz
            </a>
        </div>
    </div>

    <!-- Filters Strip -->
    <form method="GET" action="{{ route('teacher.quizzes.index') }}" class="quizzes-filter-panel" id="quiz-filter-form">
        <div class="quizzes-filter-grid">
            <!-- Search by title -->
            <div class="filter-item filter-search">
                <label for="filter-search" class="filter-label">Search</label>
                <div class="search-input-wrap">
                    {{-- <x-icon name="search" size="14" class="search-icon" /> --}}
                    <input type="text" id="filter-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search title or subject...">
                </div>
            </div>

            <!-- Class & Section Filter -->
            <div class="filter-item">
                <label for="filter-class" class="filter-label">Class & Section</label>
                <select name="class_section" id="filter-class" onchange="this.form.submit()">
                    <option value="">All Classes</option>
                    @php
                        $uniqueClassSections = collect($assignments)->unique(fn($a) => $a->class_id . '-' . $a->section_id);
                        $currentCs = ($filters['class_id'] && $filters['section_id']) ? $filters['class_id'].'-'.$filters['section_id'] : '';
                    @endphp
                    @foreach($uniqueClassSections as $item)
                        @php $val = $item->class_id . '-' . $item->section_id; @endphp
                        <option value="{{ $val }}" @selected($currentCs === $val)>
                            {{ $item->schoolClass->name }} · {{ $item->section->name }}
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="class_id" id="hidden-class-id" value="{{ $filters['class_id'] ?? '' }}">
                <input type="hidden" name="section_id" id="hidden-section-id" value="{{ $filters['section_id'] ?? '' }}">
            </div>

            <!-- Subject Filter -->
            <div class="filter-item">
                <label for="filter-subject" class="filter-label">Subject</label>
                <select name="subject_id" id="filter-subject" onchange="this.form.submit()">
                    <option value="">All Subjects</option>
                    @php
                        $uniqueSubjects = collect($assignments)->unique('subject_id');
                    @endphp
                    @foreach($uniqueSubjects as $item)
                        <option value="{{ $item->subject_id }}" @selected(($filters['subject_id'] ?? '') == $item->subject_id)>
                            {{ $item->subject->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Assessment Type Filter -->
            <div class="filter-item">
                <label for="filter-type" class="filter-label">Type</label>
                <select name="type" id="filter-type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    @foreach($types as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div class="filter-item">
                <label for="filter-status" class="filter-label">Status</label>
                <select name="status" id="filter-status" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Marks pending</option>
                    <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Completed</option>
                    <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Closed</option>
                </select>
            </div>

            @if(array_filter($filters))
                <div class="filter-item filter-reset-wrap">
                    <a href="{{ route('teacher.quizzes.index') }}" class="button-secondary btn-clear-filters">Reset</a>
                </div>
            @endif
        </div>
    </form>

    <!-- Quizzes Table Card -->
    <div class="quizzes-table-card">
        <div class="overflow-x-auto">
            <table class="quizzes-table" aria-label="Quizzes and assessments list">
                <thead>
                    <tr>
                        <th scope="col" style="min-width: 220px;">Title & Type</th>
                        <th scope="col">Class & Subject</th>
                        <th scope="col">Date</th>
                        <th scope="col" class="text-center">Total Marks</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" style="min-width: 170px;">Progress</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($quizzes as $quiz)
                    @php
                        $compStatus = $quiz->computedStatus();
                        $activeCount = $quiz->activeStudentsCount();
                        $enteredCount = $quiz->enteredCount();
                        $percentProgress = $activeCount > 0 ? min(100, round(($enteredCount / $activeCount) * 100)) : 0;
                    @endphp
                    <tr class="quiz-row">
                        <!-- Title & Type -->
                        <td>
                            <div class="quiz-title-cell">
                                <a href="{{ route('teacher.quizzes.show', $quiz) }}" class="quiz-title-link">
                                    {{ $quiz->title }}
                                </a>
                                <div class="quiz-meta-pills">
                                    <span class="quiz-type-pill">{{ $quiz->typeLabel() }}</span>
                                    <span class="quiz-session-pill">Session {{ $quiz->session->name }}</span>
                                </div>
                            </div>
                        </td>

                        <!-- Class & Subject -->
                        <td>
                            <div class="quiz-class-cell">
                                <strong>{{ $quiz->schoolClass->name }} · {{ $quiz->section->name }}</strong>
                                <span>{{ $quiz->subject->name }}</span>
                            </div>
                        </td>

                        <!-- Date -->
                        <td>
                            <time datetime="{{ $quiz->date->toDateString() }}" class="quiz-date-text">
                                {{ $quiz->date->format('d M Y') }}
                            </time>
                        </td>

                        <!-- Total Marks -->
                        <td class="text-center">
                            <span class="quiz-marks-badge">
                                {{ (float) $quiz->total_marks }} <small class="text-muted">pts</small>
                            </span>
                        </td>

                        <!-- Status Badge -->
                        <td class="text-center">
                            @if($compStatus === 'draft')
                                <span class="badge badge-neutral"><span class="my-status-dot dot-mute"></span> Draft</span>
                            @elseif($compStatus === 'pending')
                                <span class="badge badge-warn"><span class="my-status-dot dot-warn"></span> Marks pending</span>
                            @elseif($compStatus === 'completed')
                                <span class="badge badge-ok"><span class="my-status-dot dot-ok"></span> Completed</span>
                            @elseif($compStatus === 'closed')
                                <span class="badge badge-muted">Closed</span>
                            @endif
                        </td>

                        <!-- Progress -->
                        <td>
                            <div class="quiz-progress-cell">
                                <div class="quiz-progress-labels">
                                    <span><strong>{{ $enteredCount }}</strong> / {{ $activeCount }} entered</span>
                                    <span>{{ $percentProgress }}%</span>
                                </div>
                                <div class="quiz-progress-bar">
                                    <div class="quiz-progress-fill {{ $compStatus === 'completed' ? 'fill-ok' : '' }}" style="width: {{ $percentProgress }}%;"></div>
                                </div>
                            </div>
                        </td>

                        <!-- Actions -->
                        <td class="text-right">
                            <div class="quiz-action-buttons">
                                <a href="{{ route('teacher.quizzes.marks', $quiz) }}" class="button-secondary btn-table-action btn-action-marks" title="Enter or edit student marks">
                                    <x-icon name="clipboard" size="13" />
                                    <span>{{ $compStatus === 'completed' ? 'Edit Marks' : 'Enter Marks' }}</span>
                                </a>

                                <a href="{{ route('teacher.quizzes.show', $quiz) }}" class="button-secondary btn-table-action" title="View assessment result sheet">
                                    <x-icon name="chart" size="13" />
                                    <span>Results</span>
                                </a>

                                <div class="quiz-dropdown-wrap">
                                    <details class="quiz-more-details">
                                        <summary class="btn-more-actions" aria-label="More actions for {{ $quiz->title }}">•••</summary>
                                        <div class="quiz-dropdown-menu">
                                            <a href="{{ route('teacher.quizzes.edit', $quiz) }}">
                                                <x-icon name="settings" size="13" /> Edit Settings
                                            </a>
                                            <form method="POST" action="{{ route('teacher.quizzes.duplicate', $quiz) }}" style="margin: 0;">
                                                @csrf
                                                <button type="submit" class="dropdown-item-btn">
                                                    <x-icon name="book" size="13" /> Duplicate
                                                </button>
                                            </form>
                                            <a href="{{ route('teacher.quizzes.export', [$quiz, 'csv']) }}">
                                                <x-icon name="arrow" size="13" /> Export CSV
                                            </a>
                                            <a href="{{ route('teacher.quizzes.export', [$quiz, 'pdf']) }}" target="_blank">
                                                <x-icon name="arrow" size="13" /> Export PDF
                                            </a>
                                            @if(! $quiz->hasMarksEntered())
                                                <form method="POST" action="{{ route('teacher.quizzes.destroy', $quiz) }}" onsubmit="return confirm('Are you sure you want to delete this assessment?');" style="margin: 0;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item-btn text-danger">
                                                        Delete
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="quizzes-empty-state">
                                <x-icon name="clipboard" size="32" class="empty-icon" />
                                <p class="empty-text">No assessments found matching the selected criteria.</p>
                                <a href="{{ route('teacher.quizzes.create') }}" class="button-primary btn-empty-create">
                                    + Create your first quiz
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($quizzes->hasPages())
            <div class="quizzes-pagination-wrap">
                {{ $quizzes->links() }}
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const classSelect = document.getElementById('filter-class');
    const hiddenClass = document.getElementById('hidden-class-id');
    const hiddenSection = document.getElementById('hidden-section-id');

    if (classSelect) {
        classSelect.addEventListener('change', function () {
            const val = this.value;
            if (val) {
                const parts = val.split('-');
                hiddenClass.value = parts[0];
                hiddenSection.value = parts[1];
            } else {
                hiddenClass.value = '';
                hiddenSection.value = '';
            }
        });
    }

    // Close any other open dropdown when one is toggled
    const detailsElements = document.querySelectorAll('.quiz-more-details');
    detailsElements.forEach(details => {
        details.addEventListener('toggle', function() {
            if (this.open) {
                detailsElements.forEach(other => {
                    if (other !== this && other.open) {
                        other.open = false;
                    }
                });
            }
        });
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.quiz-more-details')) {
            detailsElements.forEach(d => d.open = false);
        }
    });
});
</script>
@endsection

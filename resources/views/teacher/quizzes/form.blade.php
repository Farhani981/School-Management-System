@extends('layouts.teacher')
@section('title', $isEdit ? 'Edit Assessment' : 'Create Assessment')

@section('content')
<div class="quizzes-module">
    <!-- Breadcrumb / Header -->
    <div class="quizzes-header-bar">
        <div>
            <div class="quiz-form-breadcrumb">
                <a href="{{ route('teacher.quizzes.index') }}">&larr; Back to Quizzes</a>
            </div>
            <h1 class="quizzes-title">{{ $isEdit ? 'Edit Assessment Settings' : 'Create New Assessment' }}</h1>
            <p class="quizzes-subtitle">
                {{ $isEdit ? 'Update details, passing threshold, or status for this assessment.' : 'Set up a new offline test, quiz, or exam for your assigned class.' }}
            </p>
        </div>
    </div>

    @if($isEdit && $hasMarks)
        <div class="quiz-lock-alert" role="alert">
            <span class="lock-icon"><x-icon name="help" size="18" /></span>
            <div>
                <strong>Marks have already been entered for this assessment.</strong>
                <p>Class assignment and total marks are locked to preserve grading integrity. You may still update title, date, passing marks, and status.</p>
            </div>
        </div>
    @endif

    <div class="quiz-form-card">
        <form method="POST" action="{{ $isEdit ? route('teacher.quizzes.update', $quiz) : route('teacher.quizzes.store') }}" id="quiz-settings-form">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="form-grid">
                <!-- Title -->
                <div class="form-group span-2">
                    <label for="quiz-title" class="form-label required">Assessment Title</label>
                    <input type="text" id="quiz-title" name="title" value="{{ old('title', $quiz->title) }}" placeholder="e.g. Unit 3 Vocabulary Quiz or Monthly Math Test" required autofocus>
                    @error('title') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <!-- Type -->
                <div class="form-group">
                    <label for="quiz-type" class="form-label required">Assessment Type</label>
                    <select id="quiz-type" name="type" required>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" @selected(old('type', $quiz->type) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <!-- Date -->
                <div class="form-group">
                    <label for="quiz-date" class="form-label required">Assessment Date</label>
                    <input type="date" id="quiz-date" name="date" value="{{ old('date', $quiz->date?->toDateString() ?? today()->toDateString()) }}" required>
                    @error('date') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <!-- Assigned Class & Subject -->
                <div class="form-group span-2">
                    <label for="assignment-id" class="form-label required">Class, Section & Subject</label>
                    @if($isEdit && $hasMarks)
                        <input type="text" class="input-readonly" value="{{ $quiz->schoolClass->name }} · {{ $quiz->section->name }} — {{ $quiz->subject->name }} (Session {{ $quiz->session->name }})" readonly disabled>
                        <p class="form-hint">Locked because students already have marks recorded.</p>
                    @else
                        <select id="assignment-id" name="assignment_id" required>
                            <option value="">Select Class and Subject...</option>
                            @foreach($assignments as $assignment)
                                <option value="{{ $assignment->id }}" @selected(old('assignment_id', $preselectedAssignmentId) == $assignment->id)>
                                    {{ $assignment->schoolClass->name }} · {{ $assignment->section->name }} &mdash; {{ $assignment->subject->name }} (Session {{ $assignment->session->name }})
                                </option>
                            @endforeach
                        </select>
                        <p class="form-hint">Only classes and subjects assigned to your teacher account are shown.</p>
                        @error('assignment_id') <span class="form-error">{{ $message }}</span> @enderror
                    @endif
                </div>

                <!-- Total Marks -->
                <div class="form-group">
                    <label for="total-marks" class="form-label required">Total Marks</label>
                    @if($isEdit && $hasMarks)
                        <input type="number" id="total-marks" class="input-readonly" value="{{ (float) $quiz->total_marks }}" readonly disabled>
                        <p class="form-hint">Locked after marks are entered.</p>
                    @else
                        <input type="number" id="total-marks" name="total_marks" step="0.5" min="0.5" max="1000" value="{{ old('total_marks', (float) ($quiz->total_marks ?: 20)) }}" required>
                        <p class="form-hint">Maximum score obtainable (decimals like 25.5 allowed).</p>
                        @error('total_marks') <span class="form-error">{{ $message }}</span> @enderror
                    @endif
                </div>

                <!-- Passing Marks -->
                <div class="form-group">
                    <label for="passing-marks" class="form-label required">Passing Marks</label>
                    <input type="number" id="passing-marks" name="passing_marks" step="0.5" min="0" value="{{ old('passing_marks', (float) ($quiz->passing_marks ?: 8)) }}" required>
                    <p class="form-hint" id="passing-hint">Minimum score required to pass.</p>
                    @error('passing_marks') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <!-- Status (on Edit) -->
                @if($isEdit)
                    <div class="form-group span-2">
                        <label for="quiz-status" class="form-label">Status</label>
                        <select id="quiz-status" name="status">
                            <option value="draft" @selected(old('status', $quiz->status) === 'draft')>Draft (Hidden from results summary)</option>
                            <option value="published" @selected(old('status', $quiz->status) === 'published')>Published (Active assessment)</option>
                            <option value="closed" @selected(old('status', $quiz->status) === 'closed')>Closed (Locked for student marks)</option>
                        </select>
                    </div>
                @endif
            </div>

            <!-- Form Buttons -->
            <div class="quiz-form-footer">
                <a href="{{ route('teacher.quizzes.index') }}" class="button-secondary">Cancel</a>
                
                @if(!$isEdit)
                    <button type="submit" name="action" value="draft" class="button-secondary">
                        Save as Draft
                    </button>
                    <button type="submit" name="action" value="publish" class="button-primary">
                        Publish & Enter Marks &rarr;
                    </button>
                @else
                    <button type="submit" class="button-primary">
                        Save Changes
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const totalInput = document.getElementById('total-marks');
    const passInput = document.getElementById('passing-marks');
    const passHint = document.getElementById('passing-hint');

    function updatePassHint() {
        if (!totalInput || !passInput) return;
        const total = parseFloat(totalInput.value) || 0;
        const pass = parseFloat(passInput.value) || 0;
        if (total > 0 && pass >= 0) {
            const pct = Math.round((pass / total) * 100);
            passHint.textContent = `Passing threshold: ${pass} / ${total} (${pct}%)`;
            if (pass > total) {
                passInput.setCustomValidity('Passing marks cannot exceed total marks.');
                passHint.style.color = 'var(--warn)';
            } else {
                passInput.setCustomValidity('');
                passHint.style.color = '';
            }
        }
    }

    if (totalInput && passInput) {
        totalInput.addEventListener('input', updatePassHint);
        passInput.addEventListener('input', updatePassHint);
        updatePassHint();
    }
});
</script>
@endsection

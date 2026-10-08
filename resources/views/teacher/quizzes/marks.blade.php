@extends('layouts.teacher')
@section('title', 'Enter Marks · ' . $quiz->title)

@section('content')
<div class="quizzes-module">
    <!-- Top Header -->
    <div class="quizzes-header-bar">
        <div>
            <div class="quiz-form-breadcrumb">
                <a href="{{ route('teacher.quizzes.index') }}">&larr; Back to Quizzes</a>
                <span class="breadcrumb-sep">/</span>
                <a href="{{ route('teacher.quizzes.show', $quiz) }}">View Results</a>
            </div>
            <h1 class="quizzes-title">Marks Entry: {{ $quiz->title }}</h1>
            <p class="quizzes-subtitle">
                {{ $quiz->schoolClass->name }} · {{ $quiz->section->name }} &middot;
                <strong>{{ $quiz->subject->name }}</strong> &middot;
                Date: {{ $quiz->date->format('d M Y') }} &middot;
                Total Marks: <strong>{{ (float) $quiz->total_marks }}</strong> &middot;
                Pass Marks: <strong>{{ (float) $quiz->passing_marks }}</strong>
            </p>
        </div>

        <div class="quizzes-header-actions">
            <!-- CSV Import Button -->
            <button type="button" class="button-secondary btn-import-csv" id="btn-open-csv-modal">
                <x-icon name="arrow" size="14" /> Import CSV
            </button>

            <a href="{{ route('teacher.quizzes.show', $quiz) }}" class="button-secondary">
                <x-icon name="chart" size="14" /> Result Sheet
            </a>
        </div>
    </div>

    <!-- Live Summary Ribbon -->
    <div class="marks-summary-ribbon" id="marks-summary-ribbon" aria-live="polite">
        <div class="summary-metric">
            <span class="metric-lbl">STUDENTS ENTERED</span>
            <div class="metric-val">
                <span id="stat-entered-count">0</span>
                <span class="metric-sub">/ {{ count($roster) }}</span>
            </div>
        </div>

        <div class="summary-divider"></div>

        <div class="summary-metric">
            <span class="metric-lbl">CLASS AVERAGE</span>
            <div class="metric-val">
                <span id="stat-average">0.0</span>
                <span class="metric-sub" id="stat-average-pct">(0%)</span>
            </div>
        </div>

        <div class="summary-divider"></div>

        <div class="summary-metric">
            <span class="metric-lbl">HIGHEST MARK</span>
            <div class="metric-val">
                <span id="stat-highest">—</span>
                <span class="metric-sub">pts</span>
            </div>
        </div>

        <div class="summary-divider"></div>

        <div class="summary-metric">
            <span class="metric-lbl">LOWEST MARK</span>
            <div class="metric-val">
                <span id="stat-lowest">—</span>
                <span class="metric-sub">pts</span>
            </div>
        </div>

        <div class="summary-divider"></div>

        <div class="summary-metric">
            <span class="metric-lbl">PASSING COUNT</span>
            <div class="metric-val">
                <span id="stat-passed">0</span>
                <span class="metric-sub" id="stat-pass-rate">(0%)</span>
            </div>
        </div>
    </div>

    <!-- Main Marks Form -->
    <form method="POST" action="{{ route('teacher.quizzes.marks.update', $quiz) }}" id="marks-entry-form">
        @csrf

        @if($isFinalized)
            <div class="quiz-audit-warning-box">
                <div class="warning-head">
                    <x-icon name="help" size="18" />
                    <strong>This assessment has already been finalized.</strong>
                </div>
                <p>Any adjustments to student marks will be tracked in the official audit log. You must provide a clear reason for the modification.</p>
                <div class="audit-reason-field">
                    <label for="edit-reason" class="form-label required">Reason for modifying finalized marks:</label>
                    <input type="text" id="edit-reason" name="reason" placeholder="e.g. Rechecking question 4 after student query or clerical correction" required>
                    @error('reason') <span class="form-error">{{ $message }}</span> @enderror
                </div>
            </div>
        @endif

        <div class="marks-table-card">
            <div class="marks-keyboard-tip">
                <x-icon name="clock" size="14" />
                <span><strong>Keyboard Navigation:</strong> Use <kbd>Enter</kbd>, <kbd>Tab</kbd>, or <kbd>&darr;</kbd> <kbd>&uarr;</kbd> to jump between student marks quickly. Decimal steps of 0.5 are supported.</span>
            </div>

            <div class="overflow-x-auto">
                <table class="marks-entry-table" id="marks-table">
                    <thead>
                        <tr>
                            <th style="width: 70px;">Roll #</th>
                            <th style="min-width: 200px;">Student Name</th>
                            <th style="width: 170px;">Attendance Status</th>
                            <th style="width: 170px;">Marks (Max: {{ (float) $quiz->total_marks }})</th>
                            <th style="width: 100px;" class="text-center">Percentage</th>
                            <th style="width: 80px;" class="text-center">Grade</th>
                            <th style="min-width: 180px;">Remarks (Optional)</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($roster as $index => $item)
                        @php
                            $student = $item['student'];
                            $currentMarks = old("rows.{$index}.marks_obtained", $item['marks_obtained']);
                            $currentStatus = old("rows.{$index}.attendance_status", $item['attendance_status']);
                            $currentRemarks = old("rows.{$index}.remarks", $item['remarks']);
                            $rowId = "row-{$student->id}";
                        @endphp
                        <tr class="marks-row {{ $currentStatus !== 'present' ? 'row-disabled' : '' }}" id="{{ $rowId }}" data-student-id="{{ $student->id }}" data-roll="{{ $student->roll_number }}">
                            <input type="hidden" name="rows[{{ $index }}][student_id]" value="{{ $student->id }}">

                            <!-- Roll # -->
                            <td class="cell-roll">
                                <strong>{{ $student->roll_number ?: '—' }}</strong>
                            </td>

                            <!-- Student Name -->
                            <td class="cell-name">
                                <div class="student-name-block">
                                    <span class="student-name-text">{{ $student->full_name }}</span>
                                    <span class="student-admission-sub">{{ $student->admission_no }}</span>
                                </div>
                            </td>

                            <!-- Status (Present / Absent / Exempt) -->
                            <td class="cell-status">
                                <select name="rows[{{ $index }}][attendance_status]" class="status-selector" data-row-id="{{ $rowId }}">
                                    <option value="present" @selected($currentStatus === 'present')>Present</option>
                                    <option value="absent" @selected($currentStatus === 'absent')>Absent</option>
                                    <option value="exempt" @selected($currentStatus === 'exempt')>Exempt</option>
                                </select>
                            </td>

                            <!-- Marks Input -->
                            <td class="cell-marks">
                                <div class="marks-input-wrapper">
                                    <input
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        max="{{ (float) $quiz->total_marks }}"
                                        name="rows[{{ $index }}][marks_obtained]"
                                        value="{{ $currentMarks !== null ? (float) $currentMarks : '' }}"
                                        class="mark-input"
                                        placeholder="0 - {{ (float) $quiz->total_marks }}"
                                        data-row-id="{{ $rowId }}"
                                        @disabled($currentStatus !== 'present')
                                        autocomplete="off"
                                    >
                                    <span class="marks-inline-error" hidden></span>
                                </div>
                            </td>

                            <!-- Percentage (Live Computed) -->
                            <td class="text-center cell-percentage">
                                <span class="calc-pct">—</span>
                            </td>

                            <!-- Grade (Live Computed) -->
                            <td class="text-center cell-grade">
                                <span class="calc-grade">—</span>
                            </td>

                            <!-- Remarks -->
                            <td class="cell-remarks">
                                <input
                                    type="text"
                                    name="rows[{{ $index }}][remarks]"
                                    value="{{ $currentRemarks }}"
                                    class="remarks-input"
                                    placeholder="Optional note..."
                                    maxlength="255"
                                >
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 36px;">
                                No active students found in this class and section.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Sticky Form Footer -->
            <div class="marks-footer-toolbar">
                <div class="footer-left">
                    <span id="footer-status-text" class="text-muted">Fill marks for active students.</span>
                </div>
                <div class="footer-actions">
                    <a href="{{ route('teacher.quizzes.index') }}" class="button-secondary">Cancel</a>
                    
                    <button type="submit" name="action" value="draft" class="button-secondary btn-save-draft" id="btn-save-draft">
                        Save as Draft
                    </button>

                    <button type="submit" name="action" value="finalize" class="button-primary btn-finalize" id="btn-finalize">
                        <x-icon name="check" size="14" /> Finalize Marks
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Past Audit Trail on Finalized Quizzes -->
    @if($isFinalized && $audits->isNotEmpty())
        <div class="quiz-audits-section">
            <h3 class="audits-title"><x-icon name="clock" size="16" /> Audit Log (Recent Mark Changes)</h3>
            <div class="audits-list">
                @foreach($audits as $audit)
                    <div class="audit-item">
                        <div class="audit-meta">
                            <strong>{{ $audit->student?->full_name }} (Roll #{{ $audit->student?->roll_number }})</strong>
                            <span>by {{ $audit->changedBy?->name }} on {{ $audit->created_at?->format('d M Y, h:i A') }}</span>
                        </div>
                        <div class="audit-change">
                            <span>Score: <del>{{ $audit->old_marks !== null ? $audit->old_marks : 'N/A' }}</del> &rarr; <strong>{{ $audit->new_marks !== null ? $audit->new_marks : 'N/A' }}</strong></span>
                            @if($audit->old_attendance !== $audit->new_attendance)
                                <span class="audit-sub">Attendance: {{ $audit->old_attendance }} &rarr; {{ $audit->new_attendance }}</span>
                            @endif
                        </div>
                        <p class="audit-reason">Reason: "{{ $audit->reason }}"</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

<!-- CSV Import Modal -->
<dialog id="csv-modal" class="quiz-modal" aria-labelledby="csv-modal-title">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="csv-modal-title">Import Marks from CSV</h3>
            <button type="button" class="btn-close-modal" id="btn-close-csv-modal" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <p class="modal-intro">
                Upload a CSV file containing student roll numbers and marks. Columns must include <code>roll_no</code> and <code>marks</code>.
            </p>
            <div class="csv-upload-box">
                <input type="file" id="csv-file-input" accept=".csv,text/csv,text/plain">
            </div>
            
            <div id="csv-preview-container" hidden>
                <div class="csv-preview-summary">
                    <span id="csv-valid-count" class="badge badge-ok">0 Valid</span>
                    <span id="csv-error-count" class="badge badge-warn">0 Errors</span>
                </div>
                <div id="csv-errors-list" class="csv-errors-box" hidden></div>
                <div class="csv-preview-table-wrap">
                    <table class="csv-preview-table">
                        <thead>
                            <tr><th>Roll #</th><th>Student Name</th><th>Marks</th></tr>
                        </thead>
                        <tbody id="csv-preview-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button-secondary" id="btn-cancel-csv">Cancel</button>
            <button type="button" class="button-primary" id="btn-apply-csv" disabled>Apply Marks to Table</button>
        </div>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const totalMarks = {{ (float) $quiz->total_marks }};
    const passingMarks = {{ (float) $quiz->passing_marks }};
    const marksForm = document.getElementById('marks-entry-form');
    let formDirty = false;

    // Track unsaved changes
    marksForm.addEventListener('input', () => formDirty = true);
    marksForm.addEventListener('change', () => formDirty = true);

    window.addEventListener('beforeunload', function (e) {
        if (formDirty) {
            e.preventDefault();
            e.returnValue = 'You have unsaved marks. Are you sure you want to leave?';
        }
    });

    marksForm.addEventListener('submit', () => formDirty = false);

    // Live Grade Calculator
    function calculateGrade(pct) {
        if (pct >= 90) return 'A+';
        if (pct >= 80) return 'A';
        if (pct >= 70) return 'B';
        if (pct >= 60) return 'C';
        if (pct >= 50) return 'D';
        return 'F';
    }

    // Update row calculations and live summary
    function updateRowAndSummary() {
        const rows = document.querySelectorAll('.marks-row');
        let enteredCount = 0;
        let presentMarksSum = 0;
        let presentCount = 0;
        let highest = null;
        let lowest = null;
        let passedCount = 0;
        let hasInvalidMarks = false;

        rows.forEach(row => {
            const statusSelect = row.querySelector('.status-selector');
            const markInput = row.querySelector('.mark-input');
            const pctSpan = row.querySelector('.calc-pct');
            const gradeSpan = row.querySelector('.calc-grade');
            const errSpan = row.querySelector('.marks-inline-error');
            const status = statusSelect.value;
            const valStr = markInput.value.trim();

            if (status !== 'present') {
                markInput.disabled = true;
                row.classList.add('row-disabled');
                pctSpan.textContent = '—';
                gradeSpan.textContent = status === 'absent' ? 'ABS' : 'EXM';
                errSpan.hidden = true;
                enteredCount++;
            } else {
                markInput.disabled = false;
                row.classList.remove('row-disabled');

                if (valStr !== '') {
                    const mark = parseFloat(valStr);
                    if (isNaN(mark) || mark < 0 || mark > totalMarks) {
                        errSpan.textContent = `0 - ${totalMarks} only`;
                        errSpan.hidden = false;
                        markInput.classList.add('input-invalid');
                        hasInvalidMarks = true;
                        pctSpan.textContent = 'Error';
                        gradeSpan.textContent = '!';
                    } else {
                        errSpan.hidden = true;
                        markInput.classList.remove('input-invalid');
                        const pct = Math.round((mark / totalMarks) * 100);
                        pctSpan.textContent = `${pct}%`;
                        gradeSpan.textContent = calculateGrade(pct);

                        enteredCount++;
                        presentMarksSum += mark;
                        presentCount++;

                        if (highest === null || mark > highest) highest = mark;
                        if (lowest === null || mark < lowest) lowest = mark;
                        if (mark >= passingMarks) passedCount++;
                    }
                } else {
                    pctSpan.textContent = '—';
                    gradeSpan.textContent = '—';
                    errSpan.hidden = true;
                    markInput.classList.remove('input-invalid');
                }
            }
        });

        // Update Ribbon Stats
        document.getElementById('stat-entered-count').textContent = enteredCount;
        
        const avgSpan = document.getElementById('stat-average');
        const avgPctSpan = document.getElementById('stat-average-pct');
        if (presentCount > 0) {
            const avg = (presentMarksSum / presentCount);
            const avgPct = Math.round((avg / totalMarks) * 100);
            avgSpan.textContent = avg.toFixed(1);
            avgPctSpan.textContent = `(${avgPct}%)`;
        } else {
            avgSpan.textContent = '0.0';
            avgPctSpan.textContent = '(0%)';
        }

        document.getElementById('stat-highest').textContent = highest !== null ? highest : '—';
        document.getElementById('stat-lowest').textContent = lowest !== null ? lowest : '—';

        const passSpan = document.getElementById('stat-passed');
        const passRateSpan = document.getElementById('stat-pass-rate');
        passSpan.textContent = passedCount;
        if (presentCount > 0) {
            const rate = Math.round((passedCount / presentCount) * 100);
            passRateSpan.textContent = `(${rate}%)`;
        } else {
            passRateSpan.textContent = '(0%)';
        }

        // Finalize button disabled if invalid or unentered
        const finalizeBtn = document.getElementById('btn-finalize');
        const totalRows = rows.length;
        if (hasInvalidMarks) {
            finalizeBtn.disabled = true;
            finalizeBtn.title = 'Please correct invalid marks before finalizing.';
        } else {
            finalizeBtn.disabled = false;
            finalizeBtn.title = '';
        }
    }

    // Event listeners on marks table
    const table = document.getElementById('marks-table');
    table.addEventListener('input', function (e) {
        if (e.target.classList.contains('mark-input')) {
            updateRowAndSummary();
        }
    });

    table.addEventListener('change', function (e) {
        if (e.target.classList.contains('status-selector') || e.target.classList.contains('mark-input')) {
            updateRowAndSummary();
        }
    });

    // Fast Keyboard Navigation
    table.addEventListener('keydown', function (e) {
        if (!e.target.classList.contains('mark-input')) return;

        const allInputs = Array.from(document.querySelectorAll('.mark-input:not([disabled])'));
        const currentIndex = allInputs.indexOf(e.target);

        if (e.key === 'Enter' || (e.key === 'ArrowDown' && !e.shiftKey)) {
            e.preventDefault();
            if (currentIndex + 1 < allInputs.length) {
                allInputs[currentIndex + 1].focus();
                allInputs[currentIndex + 1].select();
            }
        } else if (e.key === 'ArrowUp' && !e.shiftKey) {
            e.preventDefault();
            if (currentIndex - 1 >= 0) {
                allInputs[currentIndex - 1].focus();
                allInputs[currentIndex - 1].select();
            }
        }
    });

    // Initial calculation
    updateRowAndSummary();

    // CSV Modal Handling
    const modal = document.getElementById('csv-modal');
    const openBtn = document.getElementById('btn-open-csv-modal');
    const closeBtn = document.getElementById('btn-close-csv-modal');
    const cancelBtn = document.getElementById('btn-cancel-csv');
    const fileInput = document.getElementById('csv-file-input');
    const applyBtn = document.getElementById('btn-apply-csv');
    let parsedRows = [];

    openBtn.addEventListener('click', () => {
        fileInput.value = '';
        document.getElementById('csv-preview-container').hidden = true;
        applyBtn.disabled = true;
        modal.showModal();
    });

    function closeModal() {
        modal.close();
    }
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('csv_file', file);
        formData.append('_token', '{{ csrf_token() }}');

        fetch("{{ route('teacher.quizzes.marks.import', $quiz) }}", {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            }
        })
        .then(res => res.json())
        .then(data => {
            const previewContainer = document.getElementById('csv-preview-container');
            const validCountSpan = document.getElementById('csv-valid-count');
            const errorCountSpan = document.getElementById('csv-error-count');
            const errorsBox = document.getElementById('csv-errors-list');
            const tbody = document.getElementById('csv-preview-tbody');

            previewContainer.hidden = false;
            validCountSpan.textContent = `${data.valid_count} Valid`;
            errorCountSpan.textContent = `${data.error_count} Errors`;

            if (data.errors && data.errors.length > 0) {
                errorsBox.hidden = false;
                errorsBox.innerHTML = '<ul>' + data.errors.map(err => `<li>${err}</li>`).join('') + '</ul>';
            } else {
                errorsBox.hidden = true;
                errorsBox.innerHTML = '';
            }

            tbody.innerHTML = '';
            parsedRows = data.matched || [];

            parsedRows.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${item.roll_number}</td><td>${item.name}</td><td><strong>${item.marks}</strong></td>`;
                tbody.appendChild(tr);
            });

            applyBtn.disabled = parsedRows.length === 0;
        })
        .catch(err => {
            alert('Failed to parse CSV file. Please make sure the format is valid.');
        });
    });

    applyBtn.addEventListener('click', function () {
        parsedRows.forEach(item => {
            const row = document.querySelector(`.marks-row[data-student-id="${item.student_id}"]`);
            if (row) {
                const statusSelect = row.querySelector('.status-selector');
                const markInput = row.querySelector('.mark-input');
                statusSelect.value = 'present';
                markInput.disabled = false;
                markInput.value = item.marks;
            }
        });

        updateRowAndSummary();
        formDirty = true;
        closeModal();
    });
});
</script>
@endsection

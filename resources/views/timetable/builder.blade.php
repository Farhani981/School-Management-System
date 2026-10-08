@extends('layouts.admin')
@section('title', 'Class Timetable Builder')
@section('content')
@include('timetable.partials.header', [
    'heading' => 'School Class Timetable',
    'description' => 'Set one day’s periods for each class, then apply the same routine across the teaching week.'
])

<!-- Filter & Selection Bar -->
<div class="panel student-form" style="padding: 18px 24px; border-radius: 18px; border: 1px solid var(--line); background: var(--card); margin-bottom: 20px;">
    <form method="GET" action="{{ route('timetable.builder') }}" style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
        <div style="flex: 1; min-width: 160px;">
            <label for="b-session" style="display: block; font-size: 12px; font-weight: 600; color: var(--mute); margin-bottom: 6px;">Academic Session</label>
            <select id="b-session" name="session_id" class="form-select" onchange="this.form.submit()" style="width: 100%; padding: 8px 12px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
                @foreach($sessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $selectedSessionId)>{{ $s->name }} @if($s->is_current)(Current)@endif</option>
                @endforeach
            </select>
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label for="b-class" style="display: block; font-size: 12px; font-weight: 600; color: var(--mute); margin-bottom: 6px;">Class</label>
            <select id="b-class" name="class_id" class="form-select" onchange="this.form.submit()" style="width: 100%; padding: 8px 12px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
                @foreach($classes as $c)
                    <option value="{{ $c->id }}" @selected($selectedClass && $c->id === $selectedClass->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label for="b-section" style="display: block; font-size: 12px; font-weight: 600; color: var(--mute); margin-bottom: 6px;">Section</label>
            <select id="b-section" name="section_id" class="form-select" onchange="this.form.submit()" style="width: 100%; padding: 8px 12px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
                @if($selectedClass)
                    @foreach($sections as $sec)
                        <option value="{{ $sec->id }}" @selected($selectedSection && $sec->id === $selectedSection->id)>Section {{ $sec->name }}</option>
                    @endforeach
                @endif
            </select>
        </div>

        <div class="admission-field">
            <label for="routine-view">View</label>
            <select id="routine-view" name="view" onchange="this.form.requestSubmit()"><option value="daily" @selected($viewMode === 'daily')>Daily class routine</option><option value="weekly" @selected($viewMode === 'weekly')>Full week</option></select>
        </div>
        <div class="admission-field">
            <label for="routine-day">Day to manage</label>
            <select id="routine-day" name="day" onchange="this.form.requestSubmit()">@foreach(\App\Models\TimetableEntry::DAYS as $dayNumber => $dayLabel)<option value="{{ $dayNumber }}" @selected($selectedDay === $dayNumber)>{{ $dayLabel }}</option>@endforeach</select>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit" class="button-secondary" style="height: 40px; padding: 0 16px;">Load</button>
            <button type="button" onclick="window.print()" class="button-secondary" style="height: 40px; padding: 0 16px; display: inline-flex; align-items: center; gap: 6px;">
                <x-icon name="clipboard" size="14" /> Print
            </button>
        </div>
    </form>
</div>

@if(!$selectedClass || !$selectedSection)
    <div class="panel" style="padding: 40px; text-align: center; color: var(--mute);">
        Please select a valid class and section to manage the timetable.
    </div>
@else
    <form class="panel student-form" method="POST" action="{{ route('timetable.routine.repeat') }}" style="margin-bottom:20px">
        @csrf
        <input type="hidden" name="session_id" value="{{ $selectedSessionId }}">
        <input type="hidden" name="class_id" value="{{ $selectedClass->id }}">
        <input type="hidden" name="section_id" value="{{ $selectedSection->id }}">
        <input type="hidden" name="source_day" value="{{ $selectedDay }}">
        <p class="photo-help">Fill {{ \App\Models\TimetableEntry::DAYS[$selectedDay] }} below, then apply its subjects and teachers to every teaching day. The same first-period teacher can take attendance each day after publishing.</p>
        <div class="form-actions">
            <label for="routine-teaching-days">Teaching days</label>
            <select id="routine-teaching-days" name="teaching_days"><option value="6">Monday–Saturday (6 days)</option><option value="7">Monday–Sunday (7 days)</option></select>
            <button type="submit" class="button-primary">Apply Daily Routine</button>
        </div>
    </form>
    <!-- Top Action Bar for this Section -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 16px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <h2 style="font-family: 'Segoe UI', Arial, sans-serif; font-size: 20px; margin: 0; color: var(--ink);">
                {{ $selectedClass->name }} &middot; Section {{ $selectedSection->name }}
            </h2>
            @if($isPublished)
                <span class="badge status-ok" style="padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 600;">
                    ● Published
                </span>
            @else
                <span class="badge status-warn" style="padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 600;">
                    Draft
                </span>
            @endif
            <span style="font-size: 13px; color: var(--mute);">
                ({{ $totalAssigned }} {{ \Illuminate\Support\Str::plural('period', $totalAssigned) }} allocated)
            </span>
        </div>

        <div>
            @if($isPublished)
                <form method="POST" action="{{ route('timetable.unpublish') }}" style="display: inline;" onsubmit="return confirm('Revert this timetable to draft? Teachers will no longer see it as active.');">
                    @csrf
                    <input type="hidden" name="session_id" value="{{ $selectedSessionId }}">
                    <input type="hidden" name="class_id" value="{{ $selectedClass->id }}">
                    <input type="hidden" name="section_id" value="{{ $selectedSection->id }}">
                    <button type="submit" class="button-secondary" style="height: 38px;">
                        Revert to Draft
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('timetable.publish') }}" style="display: inline;">
                    @csrf
                    <input type="hidden" name="session_id" value="{{ $selectedSessionId }}">
                    <input type="hidden" name="class_id" value="{{ $selectedClass->id }}">
                    <input type="hidden" name="section_id" value="{{ $selectedSection->id }}">
                    <button type="submit" class="button-primary" style="height: 38px; display: inline-flex; align-items: center; gap: 6px;">
                        <x-icon name="check" size="14" /> Publish Timetable
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if($validAssignments->isEmpty())
        <div class="panel" style="padding: 16px 20px; border-radius: 12px; background: var(--warn-soft, #fff7ed); border: 1px solid var(--warn, #f97316); color: var(--warn, #c2410c); margin-bottom: 20px; font-size: 13px;">
            <strong>Warning:</strong> No teacher assignments found for {{ $selectedClass->name }} - Section {{ $selectedSection->name }}. You must assign teachers and subjects in <a href="{{ route('teachers.assignments.index') }}" style="text-decoration: underline; font-weight: 600;">Teacher Assignments</a> before you can allocate periods.
        </div>
    @endif

    <!-- Weekly Grid -->
    <div class="panel student-form" style="padding: 14px; border-radius: 18px; border: 1px solid var(--line); background: var(--card);">
        <div class="overflow-x-auto">
            <table class="timetable-grid" style="width: 100%; border-collapse: separate; border-spacing: 6px;">
                <thead>
                    <tr>
                        <th style="padding: 12px; background: var(--bg); border-radius: 10px; font-size: 12px; color: var(--mute); text-transform: uppercase; width: 110px;">
                            Day / Period
                        </th>
                        @foreach($periods as $p)
                            <th style="padding: 10px; background: var(--bg); border-radius: 10px; text-align: center; min-width: 130px;">
                                <div style="font-weight: 700; font-size: 13px; color: var(--ink);">{{ $p->name }}</div>
                                <div style="font-size: 12px; color: var(--mute); font-weight: 500;">{{ $p->formatted_time_range }}</div>
                                @if($p->is_break)
                                    <span style="font-size: 12px; background: var(--line); color: var(--mute); padding: 1px 6px; border-radius: 99px; text-transform: uppercase;">Break</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($days as $dayNum => $dayName)
                        <tr>
                            <!-- Day Header Column -->
                            <td style="padding: 12px; background: var(--bg); border-radius: 10px; font-weight: 700; font-size: 13px; color: var(--ink); text-align: center;">
                                {{ $dayName }}
                            </td>

                            <!-- Period Cells -->
                            @foreach($periods as $period)
                                @php
                                    $entry = $grid[$dayNum][$period->id] ?? null;
                                @endphp
                                <td style="padding: 6px; border-radius: 10px; vertical-align: top; @if($period->is_break) background: var(--bg); opacity: 0.7; @else background: var(--card); border: 1px dashed var(--line); @endif min-height: 80px;">
                                    @if($period->is_break)
                                        <div style="height: 100%; display: flex; align-items: center; justify-content: center; color: var(--mute); font-size: 12px; font-style: italic; min-height: 60px;">
                                            Break
                                        </div>
                                    @elseif($entry)
                                        <!-- Assigned Slot Card -->
                                        <div style="background: var(--soft); border-left: 3px solid var(--teal); border-radius: 8px; padding: 8px 10px; position: relative;">
                                            <div style="font-weight: 700; font-size: 13px; color: var(--ink); margin-bottom: 2px;">
                                                {{ $entry->subject->name }}
                                            </div>
                                            <div style="font-size: 12px; color: var(--teal); font-weight: 600;">
                                                {{ $entry->teacher->name }}
                                            </div>
                                            @if($entry->room)
                                                <div style="font-size: 12px; color: var(--mute); margin-top: 2px;">
                                                    Room: {{ $entry->room }}
                                                </div>
                                            @endif
                                            
                                            <!-- Delete Slot Action -->
                                            <form method="POST" action="{{ route('timetable.entries.destroy', $entry) }}" style="position: absolute; top: 4px; right: 4px;" onsubmit="return confirm('Remove this period assignment?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" aria-label="Remove slot" style="border: none; background: transparent; color: var(--danger); font-size: 16px; line-height: 1; cursor: pointer; padding: 2px;">
                                                    &times;
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <!-- Empty / Add Slot Button -->
                                        <div style="height: 100%; min-height: 60px; display: flex; align-items: center; justify-content: center;">
                                            @if($validAssignments->isNotEmpty())
                                                <button type="button" class="btn-slot-assign" 
                                                        data-day="{{ $dayNum }}" 
                                                        data-day-name="{{ $dayName }}" 
                                                        data-period-id="{{ $period->id }}" 
                                                        data-period-name="{{ $period->name }} ({{ $period->formatted_time_range }})"
                                                        style="border: 1px dashed var(--teal); background: transparent; color: var(--teal); border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                    + Add
                                                </button>
                                            @else
                                                <span style="font-size: 12px; color: var(--mute);">—</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<!-- Slot Assignment Modal Dialog -->
<dialog id="slot-modal" class="progress-modal-backdrop" aria-labelledby="modal-slot-title" style="border: none; padding: 0; background: transparent; width: 100%; max-width: 480px;">
    <div style="background: var(--card); border: 1px solid var(--line); border-radius: 18px; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.15);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 id="modal-slot-title" style="margin: 0; font-family: 'Segoe UI', Arial, sans-serif; font-size: 18px; color: var(--ink);">
                Assign Period Slot
            </h3>
            <button type="button" aria-label="Close Assign Period Slot" onclick="document.getElementById('slot-modal').close()" style="border: none; background: transparent; font-size: 22px; cursor: pointer; color: var(--mute);">&times;</button>
        </div>

        <form method="POST" action="{{ route('timetable.entries.store') }}">
            @csrf
            <input type="hidden" name="session_id" value="{{ $selectedSessionId }}">
            <input type="hidden" name="class_id" value="{{ $selectedClass?->id }}">
            <input type="hidden" name="section_id" value="{{ $selectedSection?->id }}">
            <input type="hidden" name="day_of_week" id="modal-day-input">
            <input type="hidden" name="period_id" id="modal-period-input">

            <div style="margin-bottom: 16px;">
                <p id="modal-slot-info" style="font-size: 13px; color: var(--mute); margin: 0 0 12px; background: var(--bg); padding: 8px 12px; border-radius: 8px;"></p>
            </div>

            <!-- Subject & Assigned Teacher Selection -->
            <div style="margin-bottom: 16px;">
                <label for="assignment-select" style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">
                    Subject &amp; Assigned Teacher <span style="color: var(--danger);">*</span>
                </label>
                <select id="assignment-select" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;" onchange="updateAssignmentFields(this)">
                    <option value="">-- Choose Subject &amp; Teacher --</option>
                    @foreach($validAssignments as $a)
                        <option value="{{ $a->subject_id }}:{{ $a->teacher_id }}" data-subject="{{ $a->subject_id }}" data-teacher="{{ $a->teacher_id }}">
                            {{ $a->subject->name }} &mdash; {{ $a->teacher->name }}
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="subject_id" id="modal-subject-id">
                <input type="hidden" name="teacher_id" id="modal-teacher-id">
                <span style="font-size: 12px; color: var(--mute); display: block; margin-top: 4px;">
                    Only teachers formally assigned to this class and section are selectable.
                </span>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="room-input" style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">
                    Room / Lab (Optional)
                </label>
                <input type="text" id="room-input" name="room" placeholder="e.g. Room 102, Science Lab" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="button-secondary" onclick="document.getElementById('slot-modal').close()">Cancel</button>
                <button type="submit" class="button-primary">Save Slot</button>
            </div>
        </form>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('slot-modal');
    document.querySelectorAll('.btn-slot-assign').forEach(btn => {
        btn.addEventListener('click', () => {
            const day = btn.dataset.day;
            const dayName = btn.dataset.dayName;
            const periodId = btn.dataset.periodId;
            const periodName = btn.dataset.periodName;

            document.getElementById('modal-day-input').value = day;
            document.getElementById('modal-period-input').value = periodId;
            document.getElementById('modal-slot-info').textContent = `${dayName} · ${periodName}`;
            document.getElementById('assignment-select').value = '';
            document.getElementById('modal-subject-id').value = '';
            document.getElementById('modal-teacher-id').value = '';
            document.getElementById('room-input').value = '';

            modal.showModal();
        });
    });
});

function updateAssignmentFields(select) {
    const selected = select.options[select.selectedIndex];
    if (selected && selected.dataset.subject) {
        document.getElementById('modal-subject-id').value = selected.dataset.subject;
        document.getElementById('modal-teacher-id').value = selected.dataset.teacher;
    } else {
        document.getElementById('modal-subject-id').value = '';
        document.getElementById('modal-teacher-id').value = '';
    }
}
</script>
@endsection

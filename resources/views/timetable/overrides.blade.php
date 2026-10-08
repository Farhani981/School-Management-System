@extends('layouts.admin')
@section('title', 'Timetable Substitutions & Overrides')
@section('content')
@include('timetable.partials.header', [
    'heading' => 'Timetable Substitutions & Overrides',
    'description' => 'Manage one-day teacher substitutions, period cancellations, and swaps.'
])

<!-- Date Filter & Quick Add Bar -->
<div class="panel student-form" style="padding: 20px 24px; border-radius: 18px; border: 1px solid var(--line); background: var(--card); margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px;">
        <form method="GET" action="{{ route('timetable.overrides.index') }}" style="display: flex; align-items: flex-end; gap: 12px;">
            <div>
                <label for="ov-date" style="display: block; font-size: 12px; font-weight: 600; color: var(--mute); margin-bottom: 6px;">Select Date</label>
                <input type="date" id="ov-date" name="date" value="{{ $selectedDate }}" onchange="this.form.submit()" style="padding: 8px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
            </div>
            <button type="submit" class="button-secondary" style="height: 38px;">View Date</button>
        </form>

        <button type="button" class="button-primary" onclick="document.getElementById('add-override-modal').showModal()" style="display: inline-flex; align-items: center; gap: 6px; height: 38px;">
            <span aria-hidden="true">+</span> Record Substitution
        </button>
    </div>
</div>

<!-- Overrides List -->
<div class="panel student-form" style="padding: 24px; border-radius: 18px; border: 1px solid var(--line); background: var(--card);">
    <h3 style="margin: 0 0 16px; font-family: 'Segoe UI', Arial, sans-serif; font-size: 18px; color: var(--ink);">
        Overrides for {{ \Carbon\Carbon::parse($selectedDate)->format('l, d M Y') }}
    </h3>

    <div class="overflow-x-auto">
        <table class="student-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 1px solid var(--line); text-align: left; font-size: 12px; color: var(--mute); text-transform: uppercase;">
                    <th style="padding: 12px 16px;">Class &amp; Section</th>
                    <th style="padding: 12px 16px;">Period</th>
                    <th style="padding: 12px 16px;">Subject</th>
                    <th style="padding: 12px 16px;">Primary Teacher</th>
                    <th style="padding: 12px 16px;">Override Type</th>
                    <th style="padding: 12px 16px;">Substitute / Action</th>
                    <th style="padding: 12px 16px;">Reason</th>
                    <th style="padding: 12px 16px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($overrides as $ov)
                    <tr style="border-bottom: 1px solid var(--line); font-size: 14px;">
                        <td style="padding: 14px 16px; font-weight: 600; color: var(--ink);">
                            {{ $ov->entry->schoolClass->name }} - {{ $ov->entry->section->name }}
                        </td>
                        <td style="padding: 14px 16px; color: var(--mute);">
                            {{ $ov->entry->period->name }} ({{ $ov->entry->period->formatted_time_range }})
                        </td>
                        <td style="padding: 14px 16px; font-weight: 600; color: var(--ink);">
                            {{ $ov->entry->subject->name }}
                        </td>
                        <td style="padding: 14px 16px; color: var(--mute);">
                            {{ $ov->entry->teacher->name }}
                        </td>
                        <td style="padding: 14px 16px;">
                            <span class="badge @if($ov->type === 'substitute') status-ok @else status-warn @endif" style="padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 600;">
                                {{ $ov->type_label }}
                            </span>
                        </td>
                        <td style="padding: 14px 16px; font-weight: 600; color: var(--teal);">
                            @if($ov->substituteTeacher)
                                {{ $ov->substituteTeacher->name }}
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td style="padding: 14px 16px; color: var(--mute); font-size: 13px;">
                            {{ $ov->reason ?: 'No reason stated' }}
                        </td>
                        <td style="padding: 14px 16px; text-align: right;">
                            <form method="POST" action="{{ route('timetable.overrides.destroy', $ov) }}" onsubmit="return confirm('Remove this substitution?');" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button-secondary" style="padding: 6px 12px; font-size: 12px; color: var(--danger);">
                                    Revoke
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 36px; color: var(--mute);">
                            No substitutions or overrides scheduled for {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 16px;">
        {{ $overrides->links() }}
    </div>
</div>

<!-- Record Substitution Modal -->
<dialog id="add-override-modal" class="progress-modal-backdrop" style="border: none; padding: 0; background: transparent; width: 100%; max-width: 500px;">
    <div style="background: var(--card); border: 1px solid var(--line); border-radius: 18px; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.15);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 style="margin: 0; font-family: 'Segoe UI', Arial, sans-serif; font-size: 18px; color: var(--ink);">
                Record Daily Substitution
            </h3>
            <button type="button" onclick="document.getElementById('add-override-modal').close()" style="border: none; background: transparent; font-size: 22px; cursor: pointer; color: var(--mute);">&times;</button>
        </div>

        <form method="POST" action="{{ route('timetable.overrides.store') }}">
            @csrf
            <input type="hidden" name="date" value="{{ $selectedDate }}">

            <div style="margin-bottom: 14px;">
                <label for="scheduled-slot" style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">
                    Select Scheduled Period Slot <span style="color: var(--danger);">*</span>
                </label>
                <select id="scheduled-slot" name="entry_id" required style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
                    <option value="">-- Choose Class &amp; Period --</option>
                    @foreach($scheduledEntries as $entry)
                        <option value="{{ $entry->id }}">
                            {{ $entry->schoolClass->name }} {{ $entry->section->name }} &middot; {{ $entry->period->name }} &middot; {{ $entry->subject->name }} (Teacher: {{ $entry->teacher->name }})
                        </option>
                    @endforeach
                </select>
                @if($scheduledEntries->isEmpty())
                    <span style="font-size: 12px; color: var(--warn); display: block; margin-top: 4px;">
                        No published timetable entries found for this day of the week.
                    </span>
                @endif
            </div>

            <div style="margin-bottom: 14px;">
                <label for="override-type" style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">
                    Action Type <span style="color: var(--danger);">*</span>
                </label>
                <select id="override-type" name="type" required onchange="toggleSubstituteField(this)" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
                    <option value="substitute">Assign Substitute Teacher</option>
                    <option value="cancelled">Cancel This Period</option>
                    <option value="swapped">Swapped Period</option>
                </select>
            </div>

            <div id="substitute-field-group" style="margin-bottom: 14px;">
                <label for="substitute-teacher" style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">
                    Substitute Teacher <span style="color: var(--danger);">*</span>
                </label>
                <select id="substitute-teacher" name="substitute_teacher_id" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
                    <option value="">-- Choose Substitute Teacher --</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->employee_id }})</option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="override-reason" style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">
                    Reason
                </label>
                <textarea id="override-reason" name="reason" rows="2" placeholder="e.g. Teacher on approved medical leave" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="button-secondary" onclick="document.getElementById('add-override-modal').close()">Cancel</button>
                <button type="submit" class="button-primary">Save Substitution</button>
            </div>
        </form>
    </div>
</dialog>

<script>
function toggleSubstituteField(select) {
    const group = document.getElementById('substitute-field-group');
    const teacherSelect = document.getElementById('substitute-teacher');
    if (select.value === 'substitute') {
        group.style.display = 'block';
        teacherSelect.required = true;
    } else {
        group.style.display = 'none';
        teacherSelect.required = false;
        teacherSelect.value = '';
    }
}
</script>
@endsection

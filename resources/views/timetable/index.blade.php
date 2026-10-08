@extends('layouts.admin')
@section('title', 'Timetable Management')
@section('content')
@include('timetable.partials.header', [
    'heading' => 'Timetable Management',
    'description' => 'Create, publish, and monitor weekly period schedules for all classes and sections.'
])

<!-- KPI Summary Strip -->
<div class="summary-stat-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="padding: 18px 22px; border-radius: 18px; border: 1px solid var(--line); background: var(--card);">
        <span style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--mute); font-weight: 600;">ACTIVE SESSION</span>
        <strong style="display: block; font-size: 26px; font-family: 'Segoe UI', Arial, sans-serif; color: var(--ink); margin-top: 4px;">
            {{ $sessions->firstWhere('id', $selectedSession)?->name ?? 'Current' }}
        </strong>
        <span style="font-size: 12px; color: var(--mute);">{{ $totalSections }} total sections</span>
    </div>

    <div class="card" style="padding: 18px 22px; border-radius: 18px; border: 1px solid var(--line); background: var(--card);">
        <span style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--mute); font-weight: 600;">PUBLISHED TIMETABLES</span>
        <strong style="display: block; font-size: 26px; font-family: 'Segoe UI', Arial, sans-serif; color: var(--ok); margin-top: 4px;">
            {{ $publishedCount }} / {{ $totalSections }}
        </strong>
        <span style="font-size: 12px; color: var(--mute);">Live on teacher panels</span>
    </div>

    <div class="card" style="padding: 18px 22px; border-radius: 18px; border: 1px solid var(--line); background: var(--card);">
        <span style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--mute); font-weight: 600;">TOTAL SLOTS SCHEDULED</span>
        <strong style="display: block; font-size: 26px; font-family: 'Segoe UI', Arial, sans-serif; color: var(--teal); margin-top: 4px;">
            {{ $totalEntries }}
        </strong>
        <span style="font-size: 12px; color: var(--mute);">Across all active classes</span>
    </div>
</div>

<!-- Class Sections Directory -->
<div class="panel student-form" style="padding: 24px; border-radius: 18px; border: 1px solid var(--line); background: var(--card);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 20px;">
        <h3 style="margin: 0; font-family: 'Segoe UI', Arial, sans-serif; font-size: 18px; color: var(--ink);">
            Section Timetables
        </h3>

        <!-- Session Filter -->
        <form method="GET" action="{{ route('timetable.index') }}" style="display: flex; align-items: center; gap: 10px;">
            <label for="session-select" style="font-size: 13px; font-weight: 600; color: var(--mute);">Session:</label>
            <select id="session-select" name="session_id" class="form-select" onchange="this.form.submit()" style="padding: 8px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg); font-size: 13px;">
                @foreach($sessions as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $selectedSession)>
                        {{ $s->name }} @if($s->is_current) (Current) @endif
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="student-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 1px solid var(--line); text-align: left; font-size: 12px; color: var(--mute); text-transform: uppercase;">
                    <th style="padding: 12px 16px;">Class</th>
                    <th style="padding: 12px 16px;">Section</th>
                    <th style="padding: 12px 16px;">Scheduled Slots</th>
                    <th style="padding: 12px 16px;">Status</th>
                    <th style="padding: 12px 16px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sectionsData as $row)
                    <tr style="border-bottom: 1px solid var(--line); font-size: 14px;">
                        <td style="padding: 14px 16px; font-weight: 600; color: var(--ink);">
                            {{ $row['class']->name }}
                        </td>
                        <td style="padding: 14px 16px; color: var(--ink);">
                            <span class="badge" style="background: var(--soft); color: var(--teal); padding: 4px 10px; border-radius: 99px; font-weight: 600;">
                                Section {{ $row['section']->name }}
                            </span>
                        </td>
                        <td style="padding: 14px 16px; color: var(--mute);">
                            {{ $row['entries_count'] }} {{ \Illuminate\Support\Str::plural('period', $row['entries_count']) }}
                        </td>
                        <td style="padding: 14px 16px;">
                            @if($row['status'] === 'Published')
                                <span class="badge status-ok" style="padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 600;">
                                    ● Published
                                </span>
                            @elseif($row['status'] === 'Draft')
                                <span class="badge status-warn" style="padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 600;">
                                    Draft (Unpublished)
                                </span>
                            @else
                                <span class="badge" style="background: var(--bg); color: var(--mute); padding: 4px 10px; border-radius: 99px; font-size: 12px;">
                                    Not Configured
                                </span>
                            @endif
                        </td>
                        <td style="padding: 14px 16px; text-align: right;">
                            <a href="{{ route('timetable.builder', ['session_id' => $selectedSession, 'class_id' => $row['class']->id, 'section_id' => $row['section']->id]) }}" class="button-secondary" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; font-size: 13px; text-decoration: none;">
                                <x-icon name="clipboard" size="14" />
                                {{ $row['entries_count'] > 0 ? 'Edit Schedule' : 'Build Timetable' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 36px; color: var(--mute);">
                            No classes or sections configured yet. Add classes in <a href="{{ route('academic-setup.index') }}">Academic Setup</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

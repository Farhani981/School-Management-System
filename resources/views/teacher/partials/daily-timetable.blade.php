<div class="panel student-form">
    <div class="page-heading"><div><h2>My Daily Teaching Routine</h2><p>Lecture order, class and subject for the school week.</p></div><button type="button" class="button-secondary" onclick="window.print()">Print Routine</button></div>
    <div class="student-table-scroll">
        <table class="student-table" style="min-width:650px;width:100%">
            <thead><tr><th>Lecture / Period</th><th>Time</th><th>Class / Section</th><th>Subject</th></tr></thead>
            <tbody>
                @foreach($timetableData['periods'] as $period)
                    @php($slots = ($timetableData['routineEntries'][$period->id] ?? collect())->groupBy(fn ($entry) => $entry->class_id.':'.$entry->section_id.':'.$entry->subject_id))
                    @if($period->is_break)
                        <tr><td>{{ $period->name }}</td><td>{{ $period->formatted_time_range }}</td><td colspan="2">Break</td></tr>
                    @elseif($slots->isEmpty())
                        <tr><td>{{ $period->name }}</td><td>{{ $period->formatted_time_range }}</td><td colspan="2">Free period</td></tr>
                    @else
                        @foreach($slots as $group)
                            @php($entry = $group->first())
                            <tr><td>{{ $period->name }}</td><td>{{ $period->formatted_time_range }}</td><td>{{ $entry->schoolClass->name }} &middot; {{ $entry->section->name }}</td><td>{{ $entry->subject->name }}@if($entry->room)<small style="display:block">Room: {{ $entry->room }}</small>@endif</td></tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>

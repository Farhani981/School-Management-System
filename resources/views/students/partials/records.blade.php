<section class="panel" id="promotion"><div class="panel-heading"><h2>Student Promotion</h2></div><div class="student-form">
@if($student->status === 'Active')
<p>Select the destination class and academic session. The previous assignment stays in student history.</p>
<form method="POST" action="{{ route('students.promote', $student) }}">@csrf<div class="student-form-grid">
@foreach(['class_id' => 'New Class', 'section_id' => 'New Section', 'academic_session_id' => 'New Academic Session'] as $field => $label)<div class="admission-field"><label for="promote-{{ $field }}">{{ $label }}</label><select id="promote-{{ $field }}" name="{{ $field }}" required><option value="">Select</option>@foreach($options[$field] as $id => $name)<option value="{{ $id }}" @selected((string)old($field) === (string)$id)>{{ $name }}</option>@endforeach</select></div>@endforeach
<div class="admission-field"><label for="promotion-roll">New Roll Number</label><input id="promotion-roll" name="roll_number" required maxlength="255" value="{{ old('roll_number', $student->roll_number) }}"></div><div class="admission-field"><label for="promotion-notes">Notes (optional)</label><textarea id="promotion-notes" name="notes" maxlength="2000">{{ old('notes') }}</textarea></div></div><div class="form-actions"><a class="button-secondary" href="{{ route('academic-setup.index') }}">Academic Setup</a><button class="button-primary">Promote Student</button></div></form>
@else<p>Only active students can be promoted.</p>@endif
</div></section>
<section class="panel" id="history"><div class="panel-heading"><h2>Student History</h2></div><div class="student-form">
@forelse($histories as $entry)
<article class="history-entry"><h3>{{ $entry->event }}</h3><small>{{ $entry->created_at->format('d-m-Y, h:i A') }}</small>@if($entry->notes)<p>{{ $entry->notes }}</p>@endif
@if($entry->changes)<dl>@foreach($entry->changes as $field => $change)<div><dt>{{ ucwords(str_replace('_', ' ', $field)) }}</dt><dd>@foreach(['from', 'to'] as $side)@if(in_array($field, ['date_of_birth','admission_date']) && !empty($change[$side])){{ \Carbon\Carbon::parse($change[$side])->format('d-m-Y') }}@else{{ isset($options[$field]) ? ($options[$field][$change[$side]] ?? $change[$side] ?? 'Unassigned') : ($change[$side] ?? '—') }}@endif @if($side === 'from') &rarr; @endif @endforeach</dd></div>@endforeach</dl>@endif</article>
@empty<p>No history recorded yet. Future updates will appear here.</p>@endforelse
{{ $histories->fragment('history')->links() }}
</div></section>

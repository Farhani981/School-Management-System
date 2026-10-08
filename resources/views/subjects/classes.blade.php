@extends('layouts.admin')
@section('title','Class-wise Subjects')
@section('content')
<div class="page-heading"><div><h1>Class-wise Subjects</h1><p>Choose active subjects and configure marks for each class.</p></div></div>
@include('subjects.partials.feedback')
@include('subjects.partials.tabs')
<section class="panel student-form"><form method="GET" action="{{ route('subjects.classes.index') }}" data-auto-filters><div class="student-form-grid"><div class="admission-field"><label for="selected-class">Class</label><select id="selected-class" name="school_class_id"><option value="">Select a class</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected($selectedClass?->id===$class->id)>{{ $class->name }}</option>@endforeach</select></div><div class="form-actions"><button class="button-secondary">View Subjects</button></div></div></form>@if($classes->isEmpty())<p>Add classes in <a href="{{ route('academic-setup.index') }}">Academic Setup</a> first.</p>@endif</section>
@if($selectedClass)
<section class="panel student-form"><h2>Copy subjects from another class</h2><p class="photo-help">Replaces this class's active subject selection and copies marks, compulsory flags and display order. Existing inactive subject links are preserved.</p><form method="POST" action="{{ route('subjects.classes.copy') }}" data-confirm-action="Replace active subject assignments for {{ $selectedClass->name }} with subjects from the selected class?">@csrf<input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}"><div class="student-form-grid"><div class="admission-field"><label for="source-class">Copy from</label><select id="source-class" name="source_class_id" required><option value="">Select another class</option>@foreach($classes->where('id','!=',$selectedClass->id) as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select></div><div class="form-actions"><button class="button-secondary">Copy Subjects</button></div></div></form></section>
<form method="POST" action="{{ route('subjects.classes.store') }}" class="panel" data-confirm-action="Save subject selection and marks for {{ $selectedClass->name }}? Unchecked active subjects will be removed.">@csrf<input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}"><div class="panel-heading"><h2>{{ $selectedClass->name }} Subjects</h2></div>
<div class="student-table-scroll"><table class="student-table class-subject-table"><thead><tr><th>Select</th><th>Subject / Code</th><th>Total Marks</th><th>Pass Marks</th><th>Practical Marks</th><th>Display Order</th><th>Compulsory</th></tr></thead><tbody>
@forelse($subjects as $subject)
@php
$pivot=$assigned->get($subject->id)?->pivot;
$checked=in_array($subject->id,old('selected',$assigned->keys()->all()));
$oldRows=collect(old('subjects',[]))->keyBy('subject_id');
@endphp
<tr data-class-subject-row><td><input type="checkbox" name="selected[]" value="{{ $subject->id }}" @checked($checked) aria-label="Assign {{ $subject->name }}" data-class-subject-select></td><td>{{ $subject->name }}<small class="student-secondary">{{ $subject->code }} · {{ ucfirst($subject->type) }}</small></td>
@foreach(['total_marks'=>100,'pass_marks'=>33,'practical_marks'=>0,'display_order'=>$loop->iteration] as $field=>$default)<td><label class="sr-only" for="{{ $field }}-{{ $subject->id }}">{{ $subject->name }} {{ str_replace('_',' ',$field) }}</label><input id="{{ $field }}-{{ $subject->id }}" name="subjects[{{ $subject->id }}][{{ $field }}]" type="number" min="{{ $field==='total_marks'?1:0 }}" step="{{ $field==='display_order'?1:'0.01' }}" max="{{ $field==='display_order'?100000:99999999.99 }}" value="{{ $oldRows->get($subject->id)[$field] ?? $pivot?->$field ?? $default }}" data-class-subject-value></td>@endforeach
<td class="subject-compulsory"><input type="hidden" name="subjects[{{ $subject->id }}][is_compulsory]" value="0"><input type="checkbox" name="subjects[{{ $subject->id }}][is_compulsory]" value="1" @checked($oldRows->get($subject->id)['is_compulsory'] ?? $pivot?->is_compulsory ?? $subject->category==='compulsory') aria-label="{{ $subject->name }} is compulsory" data-class-subject-value></td>
</tr>@empty<tr><td colspan="7">No active subjects. <a href="{{ route('subjects.create') }}">Add a subject</a> to get started.</td></tr>@endforelse
</tbody></table></div><div class="student-form"><p class="photo-help">Practical marks are included in total marks. Pass and practical marks must not exceed total. Inactive subjects are hidden and their existing assignments are preserved.</p><div class="form-actions"><button class="button-primary">Save Class Subjects</button></div></div></form>
@endif
@endsection

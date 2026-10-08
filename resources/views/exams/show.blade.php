@extends('layouts.admin')
@section('title',$exam->name)
@section('content')
<div class="exams-page simple-exam-page">
<div class="page-heading"><div><div class="breadcrumb"><a href="{{ route('exams.index') }}">Examinations</a> / Datesheet</div><h1>{{ $exam->name }}</h1><p>{{ $exam->session->name }} &middot; {{ $exam->start_date->format('d M') }} &ndash; {{ $exam->end_date->format('d M Y') }}</p></div><div class="exam-actions"><span class="exam-status">{{ $exam->status_label }}</span>@if($editable)<a class="button-secondary" href="{{ route('exams.edit',$exam) }}">Edit Exam Details</a>@endif<a class="button-primary" href="{{ route('exams.results.index',$exam) }}">Student Results</a><a class="button-secondary" href="{{ route('exams.index') }}">All Exams</a></div></div>
@include('subjects.partials.feedback')
<div class="exam-schedule-status"><x-icon name="clipboard" size="24"/><div><h2>{{ $exam->datesheet_published_at ? 'Datesheet sent to teachers' : 'Create the datesheet' }}</h2><p>{{ $exam->datesheet_published_at ? 'Teachers submit marks from My Examinations. View combined student report cards in Student Results.' : 'Select subjects, marks, date, time and a teacher for each section. Then send the datesheet to teachers.' }}</p></div></div>
<nav class="simple-exam-tabs" role="tablist" aria-label="Exam classes">@foreach($exam->classes as $class)<button type="button" id="simple-tab-{{ $class->id }}" role="tab" aria-controls="simple-class-{{ $class->id }}" aria-selected="{{ $selectedClass?->id===$class->id ? 'true' : 'false' }}" tabindex="{{ $selectedClass?->id===$class->id ? '0' : '-1' }}" data-simple-tab="{{ $class->id }}">{{ $class->name }}</button>@endforeach</nav>
<form method="POST" action="{{ route('exams.datesheet.save',$exam) }}" data-simple-datesheet novalidate>@csrf
@foreach($exam->classes as $class)
@php
$sections=$exam->participations->where('class_id',$class->id);
$configured=$exam->subjects->where('class_id',$class->id)->keyBy('subject_id');
$subjects=$editable ? $class->subjects->where('status','Active') : $configured->map(fn($s)=>$s->subject);
$selected=old('classes.'.$class->id.'.selected',$configured->keys()->all());
@endphp
<section class="panel simple-exam-class" id="simple-class-{{ $class->id }}" role="tabpanel" aria-labelledby="simple-tab-{{ $class->id }}" @if($selectedClass?->id!==$class->id) hidden @endif><div class="panel-heading"><div><h2>{{ $class->name }}</h2><p>Sections: {{ $sections->map(fn($s)=>$s->section->name)->join(', ') }}</p></div><span class="badge badge-neutral">{{ $subjects->count() }} subjects</span></div>
<div class="student-table-scroll"><table class="student-table simple-datesheet-table"><thead><tr><th>Include</th><th>Subject</th><th>Theory / Max</th><th>Practical</th><th>Pass marks</th><th>Date &amp; Time</th><th>Section Teachers</th></tr></thead><tbody>
@forelse($subjects as $subject)
@php
$config=$configured->get($subject->id); $paper=$config?->schedules->first();
$prefix='classes['.$class->id.'][subjects]['.$subject->id.']';
$oldPrefix='classes.'.$class->id.'.subjects.'.$subject->id;
@endphp
<tr data-simple-subject><td><input type="checkbox" name="classes[{{ $class->id }}][selected][]" value="{{ $subject->id }}" @checked(in_array($subject->id,$selected)) @disabled(!$editable) aria-label="Include {{ $subject->name }}" data-simple-include></td><td><strong>{{ $subject->name }}</strong><small class="student-secondary">{{ $subject->code }}</small></td>
@foreach(['theory_marks'=>$config?->theory_marks ?? (($subject->pivot?->total_marks ?? 100)-($subject->pivot?->practical_marks ?? 0)), 'practical_marks'=>$config?->practical_marks ?? $subject->pivot?->practical_marks ?? 0, 'passing_marks'=>$config?->passing_marks ?? $subject->pivot?->pass_marks ?? 33] as $field=>$default)
<td><input name="{{ $prefix }}[{{ $field }}]" type="number" min="0" max="9999.99" step="0.01" value="{{ old($oldPrefix.'.'.$field,$default) }}" aria-label="{{ $class->name }} {{ $subject->name }} {{ str_replace('_',' ',$field) }}" @disabled(!$editable) data-simple-value></td>
@endforeach
<td><div class="simple-paper-date"><input name="{{ $prefix }}[exam_date]" type="date" min="{{ $exam->start_date->format('Y-m-d') }}" max="{{ $exam->end_date->format('Y-m-d') }}" value="{{ old($oldPrefix.'.exam_date',$paper?->exam_date?->format('Y-m-d') ?? $exam->start_date->format('Y-m-d')) }}" aria-label="{{ $subject->name }} exam date" @disabled(!$editable) data-simple-value><div class="simple-paper-times">
@foreach(['start_time'=>'09:00','end_time'=>'10:00'] as $field=>$default)<label><span>{{ $field==='start_time' ? 'Start' : 'End' }}</span><input name="{{ $prefix }}[{{ $field }}]" type="time" value="{{ old($oldPrefix.'.'.$field,substr($paper?->$field ?? $exam->$field ?? $default,0,5)) }}" aria-label="{{ $subject->name }} {{ str_replace('_',' ',$field) }}" @disabled(!$editable) data-simple-value></label>@endforeach
</div></div></td><td><div class="simple-section-teachers">
@foreach($sections as $section)
@php $teacherId=$config?->assignments->firstWhere('section_id',$section->section_id)?->teacher_id; @endphp
<label><span>{{ $section->section->name }}</span><select name="{{ $prefix }}[teachers][{{ $section->section_id }}]" aria-label="{{ $subject->name }} {{ $section->section->name }} teacher" @disabled(!$editable) data-simple-value><option value="">Select teacher</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(old($oldPrefix.'.teachers.'.$section->section_id,$teacherId)==$teacher->id)>{{ $teacher->user->name }}</option>@endforeach</select></label>
@endforeach
</div></td></tr>
@empty<tr><td colspan="7">No subjects configured. <a href="{{ route('subjects.classes.index',['school_class_id'=>$class->id]) }}">Add class subjects</a>.</td></tr>@endforelse
</tbody></table></div>
@if($editable)<details class="simple-exam-exception"><summary>Exam on a Sunday or school holiday?</summary><label><input type="checkbox" name="classes[{{ $class->id }}][holiday_override]" value="1" @checked(old('classes.'.$class->id.'.holiday_override'))> Allow holiday date</label><input name="classes[{{ $class->id }}][holiday_reason]" value="{{ old('classes.'.$class->id.'.holiday_reason') }}" placeholder="Reason for holiday exam" maxlength="1000"></details>@endif
</section>
@endforeach
@if($editable)<div class="panel simple-exam-submit"><div><strong>Send the datesheet when ready</strong><p>Teachers enter marks for their selected papers. Marks submission is due 7 days after each paper.</p></div><button class="button-secondary" name="action" value="save">Save Draft</button><button class="button-primary" name="action" value="publish">Send to Teachers</button></div>@endif
</form>
@if($exam->datesheet_published_at)<a class="button-secondary" href="{{ route('exams.schedule.index',$exam) }}">View / Export Datesheet</a>@endif
@if($exam->isCurrent() && !$exam->result_published_at && $exam->status!=='cancelled')<details class="exam-cancel"><summary>Cancel this examination</summary><form method="POST" action="{{ route('exams.cancel',$exam) }}" data-confirm-action="Cancel this examination?">@csrf<label for="cancel-reason">Reason</label><input id="cancel-reason" name="reason" required minlength="3" maxlength="1000"><button class="button-secondary">Cancel Examination</button></form></details>@endif
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const tabs=Array.from(document.querySelectorAll('[data-simple-tab]'));
 const openClass=id=>{tabs.forEach(tab=>{const active=tab.dataset.simpleTab===String(id);tab.setAttribute('aria-selected',String(active));tab.tabIndex=active ? 0 : -1;document.getElementById('simple-class-'+tab.dataset.simpleTab).hidden=!active;});};
 tabs.forEach((tab,index)=>{tab.addEventListener('click',()=>openClass(tab.dataset.simpleTab));tab.addEventListener('keydown',event=>{let next;if(event.key==='ArrowRight')next=(index+1)%tabs.length;else if(event.key==='ArrowLeft')next=(index+tabs.length-1)%tabs.length;else if(event.key==='Home')next=0;else if(event.key==='End')next=tabs.length-1;else return;event.preventDefault();openClass(tabs[next].dataset.simpleTab);tabs[next].focus();});});
 const form=document.querySelector('[data-simple-datesheet]');
 form.addEventListener('submit',event=>{if(!form.checkValidity()){event.preventDefault();const invalid=form.querySelector(':invalid');const panel=invalid?.closest('[role=tabpanel]');if(panel)openClass(panel.id.replace('simple-class-',''));invalid?.reportValidity();}});
 document.querySelectorAll('[data-simple-subject]').forEach(row=>{
  const check=row.querySelector('[data-simple-include]');
  if(check.disabled) return;
  const update=()=>{row.classList.toggle('is-excluded',!check.checked);row.querySelectorAll('[data-simple-value]').forEach(field=>{field.disabled=!check.checked;field.required=check.checked;});};
  check.addEventListener('change',update);update();
 });
});
</script>
@endsection

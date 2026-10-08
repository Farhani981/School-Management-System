<div class="student-filter-chips" aria-label="Active student filters"><div class="directory-active-filters">
@if(!empty($filters['missing_assignment']))<a class="directory-filter-chip" href="{{ route('students.index', array_merge($filters, ['missing_assignment'=>null,'page'=>null])) }}">Missing class, section or session <span aria-hidden="true">&times;</span></a>@endif
@foreach(['search'=>'Search','class_id'=>'Class','section_id'=>'Section','status'=>'Status','gender'=>'Gender','academic_session_id'=>'Session'] as $field=>$label)
@if(!empty($filters[$field]))
@php($display=isset($options[$field]) ? ($options[$field][$filters[$field]]??$filters[$field]) : $filters[$field])
<a class="directory-filter-chip" href="{{ route('students.index',array_merge($filters,[$field=>$field==='status' ? '' : null,'page'=>null],$field==='class_id' ? ['section_id'=>null] : [])) }}" aria-label="Remove {{ $label }} filter: {{ $display }}">{{ $label }}: {{ $display }} <span aria-hidden="true">&times;</span></a>
@endif
@endforeach
@if(collect($filters)->only(['search','class_id','section_id','status','gender','academic_session_id'])->filter()->isNotEmpty())<a class="directory-reset" href="{{ route('students.index') }}">Reset</a>@endif
</div><span class="directory-result-count"><span class="student-count">{{ number_format($students->total()) }}</span> students</span></div>

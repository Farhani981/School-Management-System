<form method="GET" action="{{ route('students.index') }}" class="student-filters directory-toolbar">
@if(!empty($filters['missing_assignment']))<input type="hidden" name="missing_assignment" value="1">@endif
<div class="directory-search"><label class="sr-only" for="student-search">Search by name, admission number, phone or roll number</label><x-icon name="search" size="16"/><input id="student-search" type="search" name="search" maxlength="100" value="{{ $filters['search']??'' }}" placeholder="Name, admission no, phone, roll no…" autocomplete="off"></div>
@foreach(['class_id'=>['Class',$classes],'section_id'=>['Section',$sections],'status'=>['Status',array_combine(\App\Models\Student::STATUSES,\App\Models\Student::STATUSES)]] as $field=>[$label,$choices])
<label class="sr-only" for="filter-{{ $field }}">{{ $label }}</label><select class="directory-inline-select" id="filter-{{ $field }}" name="{{ $field }}" aria-label="{{ $label }}"><option value="">{{ $label }}: All</option>@foreach($choices as $id=>$name)<option value="{{ $id }}" @selected((string)($filters[$field]??'')===(string)$id)>{{ $name }}</option>@endforeach</select>
@endforeach
@include('students.partials.filter-dropdown')
<label class="directory-page-size" for="per-page">Rows: <select id="per-page" name="per_page">@foreach([10,25,50,100] as $count)<option value="{{ $count }}" @selected((int)($filters['per_page']??10)===$count)>{{ $count }}</option>@endforeach</select></label>
<input type="hidden" name="sort" value="{{ $filters['sort']??'' }}"><input type="hidden" name="direction" value="{{ $filters['direction']??'asc' }}">
<noscript><button class="button-primary">Search / Apply</button></noscript>
</form>

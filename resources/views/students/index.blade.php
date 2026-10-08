@extends('layouts.admin')
@section('title','Student List')
@section('content')
<div class="student-list-page">
<div class="page-heading directory-page-heading"><div><h1>Student List</h1><div class="breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Student List</div></div><div class="student-actions directory-heading-actions"><a class="button-secondary student-import-button" href="{{ route('students.import') }}">Import</a><details class="action-dropdown directory-export"><summary class="button-secondary student-export-button">Export <x-icon name="chevron" size="12" style="transform:rotate(90deg);margin-left:6px"/></summary><div class="dropdown-menu"><a data-export="xlsx" href="{{ route('students.export',array_merge($filters,['status'=>$filters['status']??'','format'=>'xlsx'])) }}">Excel</a><a data-export="pdf" href="{{ route('students.export',array_merge($filters,['status'=>$filters['status']??'','format'=>'pdf'])) }}">PDF</a><button type="button" data-directory-print>Print</button></div></details><a href="{{ route('admissions.create') }}" class="button-primary"><span aria-hidden="true">+</span> Add Student</a></div></div>
@include('students.partials.feedback')
@if($classes->isEmpty() || $sections->isEmpty())<p class="directory-setup-notice">Academic Setup is incomplete. <a href="{{ route('academic-setup.index') }}">Add classes and sections</a>, then assign students through Edit.</p>@endif
<section class="panel student-panel directory-card" aria-label="Student directory">
@include('students.partials.filters')
@include('students.partials.filter-chips')

@include('students.partials.bulk')
<div class="directory-table-area">
<div class="directory-loading" hidden aria-hidden="true">@for($row=0;$row<6;$row++)<div class="directory-skeleton-row"><span></span><span></span><span></span><span></span></div>@endfor</div>
<div class="student-table-scroll" role="region" aria-label="Students table, scroll horizontally on smaller screens" tabindex="0">
<table class="student-table"><thead><tr><th scope="col"><input type="checkbox" data-select-all aria-label="Select all students on this page"></th><th scope="col">S.L</th>
@foreach(['Admission No'=>'admission_no','Name'=>'name','Class'=>'class','Father Name'=>null,'Phone'=>null,'Gender'=>null,'Status'=>'status','Action'=>null] as $label=>$sort)
<th scope="col" @if($sort) aria-sort="{{ ($filters['sort']??'')===$sort ? (($filters['direction']??'asc')==='asc' ? 'ascending' : 'descending') : 'none' }}" @endif>@if($sort)<a data-sort="{{ $sort }}" href="{{ route('students.index',array_merge($filters,['sort'=>$sort,'direction'=>($filters['sort']??'')===$sort && ($filters['direction']??'asc')==='asc' ? 'desc' : 'asc'])) }}">{{ $label }}<span @class(['directory-sort','is-sorted'=>($filters['sort']??'')===$sort]) aria-hidden="true"><svg width="10" height="15" viewBox="0 0 10 15" fill="currentColor"><path d="m5 1 4 5H1Z" opacity="{{ ($filters['sort']??'')===$sort && ($filters['direction']??'asc')==='asc' ? 1 : .3 }}"/><path d="m5 14 4-5H1Z" opacity="{{ ($filters['sort']??'')===$sort && ($filters['direction']??'asc')==='desc' ? 1 : .3 }}"/></svg></span></a>@else{{ $label }}@endif</th>
@endforeach</tr></thead><tbody>
@forelse($students as $student)@include('students.partials.student-row')@empty<tr><td colspan="10"><div class="students-empty"><span><x-icon name="users" size="30"/></span><h3>No students found</h3><p>Try another search or clear your filters to find students.</p><div class="directory-empty-actions"><a href="{{ route('students.index') }}" class="button-secondary">Clear filters</a><a href="{{ route('admissions.create') }}" class="button-primary">+ Add Student</a></div></div></td></tr>@endforelse
</tbody></table></div>
</div>
<div class="student-pagination"><p>Showing {{ number_format($students->firstItem()??0) }} to {{ number_format($students->lastItem()??0) }} of {{ number_format($students->total()) }} students</p>@include('students.partials.pagination')</div>
<p class="directory-load-status sr-only" role="status" aria-live="polite"></p>
</section>
<dialog id="delete-student-dialog" class="student-dialog" aria-labelledby="delete-title"><h2 id="delete-title">Delete student?</h2><p id="delete-student-message"></p><p>The student will be archived. Documents, fee records, history and attendance will be preserved.</p><form id="delete-student-form" method="POST">@csrf @method('DELETE')<div class="form-actions"><button type="button" class="button-secondary" id="cancel-student-delete">Cancel</button><button type="submit" class="button-danger">Delete Student</button></div></form></dialog>
<dialog id="directory-promote-dialog" class="student-dialog" aria-labelledby="directory-promote-title"><h2 id="directory-promote-title">Promote selected students</h2><p>Choose the destination and starting roll number. All selected students must be Active; changes are saved together.</p><form method="POST" action="{{ route('students.bulk') }}" class="student-form" id="directory-promote-form">@csrf<input type="hidden" name="action" value="promote"><div data-promote-selection></div><div class="student-form-grid">@include('students.partials.destination')</div><div class="form-actions"><button class="button-secondary" type="button" data-close-promote>Cancel</button><button class="button-primary">Confirm Promotion</button></div></form></dialog>
</div>
@endsection

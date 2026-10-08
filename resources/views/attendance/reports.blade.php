@extends('layouts.admin')
@section('title','Attendance Reports')
@section('content')
<div class="attendance-reports-page">
@include('attendance.partials.header',['heading'=>'Attendance Reports','description'=>'Daily summaries, monthly registers and attendance percentages.'])
<div class="attendance-report-intro attendance-no-print"><span class="attendance-report-eyebrow">ATTENDANCE OVERVIEW</span><h2>Every school day, at a glance.</h2><p>Explore class summaries, track monthly attendance and spot students who need attention.</p><span class="attendance-report-period">{{ $filters['from'] }} &rarr; {{ $filters['to'] }}</span></div>
<form method="GET" action="{{ route('attendance.reports.index') }}" class="panel student-form attendance-no-print"><div class="student-form-grid">@foreach(['from'=>'From','to'=>'To'] as $field=>$label)<div class="admission-field"><label for="report-{{ $field }}">{{ $label }}</label><input id="report-{{ $field }}" type="date" name="{{ $field }}" value="{{ $filters[$field] }}" max="{{ now()->toDateString() }}" required></div>@endforeach
@foreach(['school_class_id'=>['Class',$options['classes']],'section_id'=>['Section',$options['sections']],'academic_session_id'=>['Session',$options['sessions']]] as $field=>[$label,$items])<div class="admission-field"><label for="report-{{ $field }}">{{ $label }}</label><select id="report-{{ $field }}" name="{{ $field }}"><option value="">All {{ strtolower($label) }}s</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(($filters[$field] ?? '')==$item->id)>{{ $item->name }}</option>@endforeach</select></div>@endforeach</div><div class="form-actions"><button class="button-primary">Apply Filters</button></div></form>
<div class="student-actions attendance-no-print"><a class="button-secondary" href="{{ route('attendance.reports.export',['format'=>'xlsx']+$filters) }}">Export Excel</a><a class="button-secondary" href="{{ route('attendance.reports.export',['format'=>'pdf']+$filters) }}">Export PDF</a><button type="button" class="button-secondary" data-attendance-print>Print</button></div>
@include('attendance.partials.report-data')
</div>
@endsection

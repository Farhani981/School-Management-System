@extends('layouts.admin')
@section('title','Holidays')
@section('content')
@include('attendance.partials.header',['heading'=>'Holidays','description'=>'School closures appear in attendance warnings and calendars.'])
<a class="button-secondary" href="{{ route('attendance.students.index') }}">Back to Attendance</a>
<section class="panel student-form"><h2>Add Holiday</h2><form method="POST" action="{{ route('attendance.holidays.store') }}">@csrf @include('attendance.partials.holiday-fields',['holiday'=>new \App\Models\Holiday,'prefix'=>'new'])<div class="form-actions"><button class="button-primary">Add Holiday</button></div></form></section>
@foreach($holidays as $holiday)<section class="panel student-form"><details><summary>{{ $holiday->name }} · {{ $holiday->start_date->format('d M Y') }} – {{ $holiday->end_date->format('d M Y') }}</summary><form method="POST" action="{{ route('attendance.holidays.update',$holiday) }}">@csrf @method('PUT') @include('attendance.partials.holiday-fields',['prefix'=>'holiday-'.$holiday->id])<div class="form-actions"><button class="button-secondary">Save Changes</button></div></form></details><form method="POST" action="{{ route('attendance.holidays.destroy',$holiday) }}" data-confirm-action="Delete this holiday?">@csrf @method('DELETE')<button class="action-delete">Delete</button></form></section>@endforeach<div class="student-pagination">{{ $holidays->links() }}</div>
@endsection

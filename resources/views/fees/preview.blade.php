@extends('layouts.admin')
@section('title','Student Fee Preview')
@section('content')
@include('fees.partials.header',['title'=>'Student Fee Preview','description'=>$student->first_name.' '.$student->last_name.' / '.$student->admission_no])
<section class="panel student-form"><form method="GET" action="{{ route('fees.preview',$student) }}" class="teacher-filters" data-auto-filters><div class="admission-field"><label for="preview-month">Month</label><select id="preview-month" name="month">@foreach(range(1,12) as $number)<option value="{{ $number }}" @selected((int)$month===$number)>{{ \Carbon\Carbon::create(2026,$number,1)->format('F') }}</option>@endforeach</select></div><div class="admission-field"><label for="preview-year">Year</label><input id="preview-year" type="number" name="year" min="1900" max="9999" required value="{{ $year }}"></div><button class="button-secondary">Calculate</button><a class="button-secondary" href="{{ route('students.show',$student) }}#fees">Student Profile</a></form>
@include('fees.partials.calculation')
</section>
@endsection

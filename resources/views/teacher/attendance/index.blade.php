@extends('layouts.teacher')
@section('title','Attendance')
@section('content')
@include('teacher.partials.header',['heading'=>'Attendance','description'=>'Mark attendance only for your assigned class where you teach the first period today.'])
@include('teacher.partials.classes')
@endsection

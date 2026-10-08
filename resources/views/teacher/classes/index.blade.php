@extends('layouts.teacher')
@section('title','My Classes')
@section('content')
@include('teacher.partials.header',['heading'=>'My Classes'])
@include('teacher.partials.classes')
@endsection

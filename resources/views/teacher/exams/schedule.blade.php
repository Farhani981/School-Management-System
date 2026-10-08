@extends('layouts.teacher')
@section('title','My Exam Schedule')
@section('content')
<div class="exams-page">
    <div class="page-heading"><div><h1>My Exam Schedule</h1><p>Dates, times and room details for your assigned published examination papers.</p></div><a class="button-secondary" href="{{ route('teacher.exams.index') }}">My Exams</a></div>
    <section class="panel">
        <div class="student-table-scroll"><table class="student-table">
            <thead><tr><th>Date</th><th>Time</th><th>Examination / Subject</th><th>Class / Section</th><th>Room</th><th>Invigilator</th></tr></thead>
            <tbody>
                @forelse($papers as $paper)
                    <tr>
                        <td>{{ $paper->exam_date->format('d M Y') }}</td>
                        <td>{{ $paper->time_label }}</td>
                        <td>{{ $paper->examSubject->exam->name }}<small class="student-secondary">{{ $paper->examSubject->subject->name }} &middot; {{ $paper->examSubject->exam->session->name }}</small></td>
                        <td>{{ $paper->examSubject->schoolClass->name }} / {{ $paper->section->name }}</td>
                        <td>{{ $paper->room ?? 'Not set' }}</td>
                        <td>{{ $paper->invigilator?->user?->name ?? 'Not assigned' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="exam-empty"><h2>No published papers assigned yet</h2><p>Your schedule appears once the administrator publishes the datesheet and confirms your subject assignment.</p></div></td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="exam-pagination">{{ $papers->links() }}</div>
    </section>
</div>
@endsection

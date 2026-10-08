@extends('layouts.admin')
@section('title', 'School Events')
@section('content')
<div class="page-heading"><div><h1>School Events</h1><p>Manage academic calendar dates.</p></div><a class="button-primary" href="{{ route('events.create') }}">+ Add Event</a></div>
@include('students.partials.feedback')
<section class="panel student-panel">
<form method="GET" action="{{ route('events.index') }}" class="teacher-filters" data-auto-filters><div class="admission-field"><label for="event-search">Search</label><input id="event-search" name="search" value="{{ request('search') }}" placeholder="Event title"></div><button class="button-secondary">Search</button></form>
<div class="student-table-scroll"><table class="student-table"><thead><tr><th>Title</th><th>Date</th><th>End date</th><th>Type</th><th>Actions</th></tr></thead><tbody>
@forelse($events as $event)<tr><td>{{ $event->title }}</td><td>{{ $event->date->format('d-m-Y') }}</td><td>{{ $event->end_date?->format('d-m-Y') ?? '—' }}</td><td><span class="badge badge-neutral">{{ ucfirst($event->type) }}</span></td><td><div class="student-actions"><a class="action-edit" href="{{ route('events.edit', $event) }}">Edit</a><button type="button" class="action-delete" data-delete-student="{{ route('events.destroy', $event) }}" data-student-name="{{ $event->title }}">Delete</button></div></td></tr>
@empty<tr><td colspan="5"><div class="students-empty"><p>No school events yet.</p></div></td></tr>@endforelse
</tbody></table></div><div class="student-pagination">{{ $events->links() }}</div></section>
<dialog id="delete-student-dialog" class="student-dialog"><h2>Delete event?</h2><p id="delete-student-message"></p><form id="delete-student-form" method="POST">@csrf @method('DELETE')<div class="form-actions"><button type="button" class="button-secondary" id="cancel-student-delete">Cancel</button><button class="button-danger">Delete Event</button></div></form></dialog>
@endsection

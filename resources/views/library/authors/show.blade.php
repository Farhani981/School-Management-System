@extends('layouts.admin')
@section('title','Author Details')
@section('content')
<div class="page-heading"><div><h1>{{ $author->name }}</h1><p>Author Details</p></div><div class="student-actions"><a class="button-secondary" href="{{ route('library.authors.index') }}">Back to Authors</a>@can('update',$author)<a class="button-primary" href="{{ route('library.authors.edit',$author) }}">Edit Author</a>@endcan</div></div>
@include('subjects.partials.feedback')
<section class="panel"><div class="panel-heading"><h2>Author Information</h2></div><dl class="student-profile-grid">@foreach(['email'=>'Email','phone'=>'Phone','country'=>'Country','status'=>'Status'] as $field=>$label)<div><dt>{{ $label }}</dt><dd>{{ $author->$field ?? 'Not provided' }}</dd></div>@endforeach</dl><div class="library-description"><h3>Biography</h3><p>{{ $author->biography ?? 'No biography provided.' }}</p></div></section>
<section class="panel"><div class="panel-heading"><h2>Books by this author</h2></div><div class="student-table-scroll"><table class="student-table"><thead><tr><th>Book Name</th><th>ISBN</th><th>Status</th></tr></thead><tbody>@forelse($books ?? [] as $book)<tr><td><a href="{{ route('library.books.show',$book->id) }}">{{ $book->title }}</a></td><td>{{ $book->isbn ?? 'Not set' }}</td><td>{{ $book->status }}</td></tr>@empty<tr><td colspan="3"><div class="students-empty"><p>No books linked to this author yet.</p></div></td></tr>@endforelse</tbody></table></div>@if($books)<div class="student-pagination">{{ $books->links() }}</div>@endif</section>
@endsection

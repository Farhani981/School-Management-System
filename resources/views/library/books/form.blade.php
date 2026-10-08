@extends('layouts.admin')
@section('title',$book->exists ? 'Edit Book' : 'Add Book')
@section('content')
<div class="library-page">
<div class="page-heading"><div><div class="breadcrumb">Library / Books List</div><h1>{{ $book->exists ? 'Edit Book' : 'Add Book' }}</h1><p>Add basic information about books for students to read and borrow.</p></div><a class="button-secondary" href="{{ route('library.books.index') }}">Back to Books</a></div>
@include('subjects.partials.feedback')
<form class="panel student-form" method="POST" enctype="multipart/form-data" action="{{ $book->exists ? route('library.books.update',$book) : route('library.books.store') }}">@csrf @if($book->exists) @method('PUT') @endif
<fieldset class="admission-section"><legend>Book Information</legend><div class="student-form-grid">
<div class="admission-field student-address"><label for="book-title">Book Name <span class="required-mark">*</span></label><input id="book-title" name="title" maxlength="255" value="{{ old('title',$book->title) }}" required placeholder="Enter book title"></div>
<div class="admission-field"><label for="book-author">Author <span class="required-mark">*</span></label><input id="book-author" name="author_name" list="book-authors" maxlength="150" value="{{ old('author_name',$book->author?->name) }}" placeholder="Enter author name" required aria-describedby="book-author-help"><datalist id="book-authors">@foreach($authors->unique('name') as $author)<option value="{{ $author->name }}">@endforeach</datalist><p id="book-author-help" class="photo-help">Type the author's name or choose a saved name. New authors are saved with the book.</p>@error('author_name')<span class="field-error">{{ $message }}</span>@enderror</div>
<div class="admission-field"><label for="book-publisher">Publisher</label><input id="book-publisher" name="publisher_name" list="book-publishers" maxlength="150" value="{{ old('publisher_name',$book->publisher?->name) }}" placeholder="Choose or enter publisher"><datalist id="book-publishers">@foreach($publishers as $publisher)<option value="{{ $publisher->name }}">@endforeach</datalist></div>
@foreach(['isbn'=>['ISBN (optional)',30],'rack'=>['Rack No (optional)',100]] as $field=>[$label,$length])<div class="admission-field"><label for="book-{{ $field }}">{{ $label }}</label><input id="book-{{ $field }}" name="{{ $field }}" value="{{ old($field,$book->$field) }}" maxlength="{{ $length }}"></div>@endforeach
<div class="admission-field"><label for="book-status">Status</label><select id="book-status" name="status">@foreach(['Active','Inactive'] as $status)<option @selected(old('status',$book->status)===$status)>{{ $status }}</option>@endforeach</select></div>
<div class="admission-field"><label for="book-cover">Book Cover</label><input id="book-cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp"><p class="photo-help">JPG, PNG or WebP, up to 10 MB.</p></div>
<div class="admission-field student-address"><label for="book-description">Description</label><textarea id="book-description" name="description" rows="4" maxlength="5000">{{ old('description',$book->description) }}</textarea></div>
</div></fieldset><div class="form-actions"><a class="button-secondary" href="{{ route('library.books.index') }}">Cancel</a><button class="button-primary">Save Book</button></div>
</form>
@if($book->exists)
@can('create', \App\Models\LibraryBookCopy::class)
<form class="panel student-form" method="POST" action="{{ route('library.copies.store', $book) }}">
@csrf
<fieldset class="admission-section"><legend>Add Physical Copies</legend>
<p class="photo-help">Saving a book records its details. Add the physical copies you have to make it available in Issue / Return.</p>
<div class="student-form-grid">
<div class="admission-field"><label for="book-copy-quantity">Copies to Add <span class="required-mark">*</span></label><input id="book-copy-quantity" name="quantity" type="number" min="1" max="100" value="{{ old('quantity', 1) }}" required><p class="photo-help">Adds new copies to the existing inventory.</p>@error('quantity')<span class="field-error">{{ $message }}</span>@enderror</div>
<div class="admission-field"><label for="book-copy-condition">Condition</label><select id="book-copy-condition" name="condition">@foreach(\App\Models\LibraryBookCopy::CONDITIONS as $condition)<option @selected(old('condition', 'Good') === $condition)>{{ $condition }}</option>@endforeach</select></div>
</div></fieldset>
<div class="form-actions"><button class="button-primary">Add Copies</button><a class="button-secondary" href="{{ route('library.borrowing.create', ['book_id' => $book->id]) }}">Issue Book</a></div>
</form>
@endcan
@endif
</div>
@endsection

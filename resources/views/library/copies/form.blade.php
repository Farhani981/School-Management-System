@extends('layouts.admin')
@section('title', 'Add Copies')
@section('content')
<div class="library-page">
    <div class="page-heading"><div><div class="breadcrumb">Library / Books List / Add Copies</div><h1>Add Book Copies</h1><p>{{ $book->title }}</p></div><a class="button-secondary" href="{{ route('library.books.show', $book) }}">Back to Book</a></div>
    @include('subjects.partials.feedback')
    <form class="panel student-form" method="POST" action="{{ route('library.copies.store', $book) }}">
        @csrf
        <fieldset class="admission-section"><legend>Physical Copies</legend><div class="student-form-grid">
            <div class="admission-field"><label for="copy-quantity">Number of Copies <span class="required-mark">*</span></label><input id="copy-quantity" type="number" name="quantity" min="1" max="100" value="{{ old('quantity', 1) }}" required><p class="photo-help">Enter the number of physical books you have. Each copy gets its own accession number and barcode.</p></div>
            <div class="admission-field"><label for="copy-condition">Condition</label><select id="copy-condition" name="condition">@foreach(\App\Models\LibraryBookCopy::CONDITIONS as $condition)<option @selected(old('condition', 'Good') === $condition)>{{ $condition }}</option>@endforeach</select></div>
            <div class="admission-field"><label for="copy-shelf">Shelf (optional)</label><input id="copy-shelf" name="shelf" maxlength="100" value="{{ old('shelf') }}"></div>
            <div class="admission-field"><label for="copy-rack">Rack (optional)</label><input id="copy-rack" name="rack" maxlength="100" value="{{ old('rack', $book->rack) }}"></div>
        </div></fieldset>
        <div class="form-actions"><a class="button-secondary" href="{{ route('library.books.show', $book) }}">Cancel</a><button class="button-primary">Save Copies</button></div>
    </form>
</div>
@endsection

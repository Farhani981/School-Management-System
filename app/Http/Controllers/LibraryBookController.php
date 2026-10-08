<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryBookRequest;
use App\Models\Activity;
use App\Models\LibraryAuthor;
use App\Models\LibraryBook;
use App\Models\LibraryPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LibraryBookController extends Controller
{
    private function layout(Request $request): string
    {
        return $request->user()->hasAnyRole(['Admin', 'Librarian']) ? 'layouts.admin' : ($request->user()->hasRole('Teacher') ? 'layouts.teacher' : 'layouts.portal');
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', LibraryBook::class);
        $filters = $request->validate(['search' => 'nullable|string|max:150', 'author_id' => 'nullable|integer|exists:library_authors,id',
            'publisher_id' => 'nullable|integer|exists:library_publishers,id', 'status' => ['nullable', Rule::in(['Active', 'Inactive'])], 'availability' => ['nullable', Rule::in(['available', 'unavailable'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50])], 'export' => ['nullable', Rule::in(['csv'])], 'book_ids' => 'nullable|array|max:50', 'book_ids.*' => 'integer|distinct|exists:library_books,id']);
        $query = LibraryBook::withInventoryCounts()->with(['author', 'publisher']);
        if ($request->filled('search')) {
            $query->where(function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where('title', 'like', $term)->orWhere('isbn', 'like', $term)->orWhereHas('author', fn ($s) => $s->where('name', 'like', $term))
                    ->orWhereHas('publisher', fn ($s) => $s->where('name', 'like', $term));
                if (Schema::hasTable('library_book_copies')) {
                    $q->orWhereIn('id', DB::table('library_book_copies')->select('book_id')->where(fn ($s) => $s->where('accession_number', 'like', $term)->orWhere('barcode', 'like', $term)));
                }
            });
        }
        foreach (['author_id', 'publisher_id', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($request->filled('availability')) {
            if (Schema::hasTable('library_book_copies')) {
                $sub = DB::table('library_book_copies')->select('book_id')->where('status', 'Available');
                $filters['availability'] === 'available' ? $query->whereIn('id', $sub) : $query->whereNotIn('id', $sub);
            } elseif ($filters['availability'] === 'available') {
                $query->whereRaw('1=0');
            }
        }
        if ($request->input('export') === 'csv') {
            if (! empty($filters['book_ids'])) {
                $query->whereIn('id', $filters['book_ids']);
            }

            return response()->streamDownload(function () use ($query) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Book Name', 'Publisher', 'Author', 'Number', 'Rack No', 'Total Copies', 'Available', 'Issued', 'Status']);
                foreach ($query->orderBy('id')->lazy(200) as $book) {
                    $row = [$book->title, $book->publisher?->name, $book->author->name, $book->id, $book->rack, $book->copies_count, $book->available_count, $book->issued_count, $book->status];
                    fputcsv($file, array_map(fn ($v) => is_string($v) && preg_match('/^[=+@\-\t\r]/', $v) ? "'".$v : $v, $row));
                }
                fclose($file);
            }, 'library-books.csv', ['Content-Type' => 'text/csv']);
        }

        return view('library.books.index', ['books' => $query->orderBy('title')->orderBy('id')->paginate((int) ($filters['per_page'] ?? 10))->withQueryString(),
            'filters' => $filters, 'authors' => LibraryAuthor::orderBy('name')->get(), 'publishers' => LibraryPublisher::orderBy('name')->get(), 'layout' => $this->layout($request)]);
    }

    private function form(LibraryBook $book)
    {
        return view('library.books.form', ['book' => $book, 'authors' => LibraryAuthor::orderBy('name')->get(), 'publishers' => LibraryPublisher::orderBy('name')->get()]);
    }

    public function create()
    {
        Gate::authorize('create', LibraryBook::class);

        return $this->form(new LibraryBook(['status' => 'Active', 'price' => 0]));
    }

    public function edit(LibraryBook $book)
    {
        Gate::authorize('update', $book);

        return $this->form($book->load(['author', 'publisher']));
    }

    public function show(Request $request, LibraryBook $book)
    {
        Gate::authorize('view', $book);
        $book = LibraryBook::withInventoryCounts()->with(['author', 'publisher'])->findOrFail($book->id);
        $copies = Schema::hasTable('library_book_copies') ? DB::table('library_book_copies')->where('book_id', $book->id)->orderBy('id')->paginate(15) : null;

        return view('library.books.show', ['book' => $book, 'copies' => $copies, 'layout' => $this->layout($request)]);
    }

    public function store(LibraryBookRequest $request)
    {
        return $this->save($request, new LibraryBook);
    }

    public function update(LibraryBookRequest $request, LibraryBook $book)
    {
        return $this->save($request, $book);
    }

    private function save(LibraryBookRequest $request, LibraryBook $book)
    {
        $data = $request->validated();
        unset($data['cover']);
        $newCover = $request->hasFile('cover') ? $request->file('cover')->store('library/covers', 'public') : null;
        if ($request->hasFile('cover') && ! $newCover) {
            throw ValidationException::withMessages(['cover' => 'The cover could not be saved. Please try again.']);
        }
        $oldCover = $book->cover;
        try {
            $book = DB::transaction(function () use ($request, $book, $data, $newCover) {
                if ($book->exists) {
                    $book = LibraryBook::whereKey($book->id)->lockForUpdate()->firstOrFail();
                }
                $authorName = $data['author_name'] ?? null;
                unset($data['author_name']);
                if ($authorName !== null && $authorName !== '') {
                    $currentAuthor = $book->exists ? $book->author : null;
                    // Keep the same author when editing a book with an unchanged name.
                    $author = $currentAuthor && mb_strtolower($currentAuthor->name) === mb_strtolower($authorName)
                        ? $currentAuthor
                        : LibraryAuthor::whereRaw('LOWER(name) = ?', [mb_strtolower($authorName)])->orderBy('id')->first();
                    if (!$author) {
                        $author = LibraryAuthor::create(['name' => $authorName, 'status' => 'Active']);
                        Activity::create(['description' => 'Library author created: '.$author->name, 'type' => 'library',
                            'subject' => 'LibraryAuthor:'.$author->id, 'user_id' => $request->user()->id, 'created_at' => now()]);
                    }
                    $data['author_id'] = $author->id;
                }
                $publisherName = $data['publisher_name'] ?? null;
                unset($data['publisher_name']);
                $data['publisher_id'] = $publisherName ? LibraryPublisher::firstOrCreate(['name' => $publisherName], ['status' => 'Active'])->id : null;
                if ($newCover) {
                    $data['cover'] = $newCover;
                }
                $action = $book->exists ? 'updated' : 'created';
                $book->fill($data)->save();
                $this->record($request, $book, $action);

                return $book;
            });
        } catch (\Throwable $e) {
            if ($newCover) {
                Storage::disk('public')->delete($newCover);
            } throw $e;
        }
        if ($newCover && $oldCover && $oldCover !== $newCover) {
            Storage::disk('public')->delete($oldCover);
        }

        return redirect()->route('library.books.show', $book)->with('success', 'Book saved.');
    }

    public function destroy(Request $request, LibraryBook $book)
    {
        Gate::authorize('delete', $book);
        $cover = null;
        $blocked = DB::transaction(function () use ($request, $book, &$cover) {
            $book = LibraryBook::whereKey($book->id)->lockForUpdate()->firstOrFail();
            foreach (['library_book_copies', 'library_transactions', 'library_reservations'] as $table) {
                if (Schema::hasTable($table) && DB::table($table)->where('book_id', $book->id)->exists()) {
                    return true;
                }
            }
            $cover = $book->cover;
            $this->record($request, $book, 'deleted');
            $book->delete();

            return false;
        });
        if (! $blocked && $cover) {
            Storage::disk('public')->delete($cover);
        }

        return redirect()->route('library.books.index')->with($blocked ? 'error' : 'success', $blocked ? 'This book has copies or library history and cannot be deleted.' : 'Book deleted.');
    }

    private function record(Request $request, LibraryBook $book, string $action): void
    {
        Activity::create(['description' => 'Library book '.$action.': '.$book->title, 'type' => 'library', 'subject' => 'LibraryBook:'.$book->id, 'user_id' => $request->user()->id, 'created_at' => now()]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryAuthorRequest;
use App\Models\Activity;
use App\Models\LibraryAuthor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LibraryAuthorController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', LibraryAuthor::class);
        $filters = $request->validate([
            'search' => 'nullable|string|max:150',
            'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
        ]);
        $query = LibraryAuthor::query()->select('library_authors.*');
        if (Schema::hasTable('library_books')) {
            $query->selectSub(DB::table('library_books')->selectRaw('COUNT(*)')->whereColumn('author_id', 'library_authors.id'), 'books_count');
        } else {
            $query->selectRaw('0 as books_count');
        }
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%')
                ->orWhere('email', 'like', '%'.$filters['search'].'%')->orWhere('country', 'like', '%'.$filters['search'].'%'));
        }
        if ($request->filled('status')) {
            $query->where('status', $filters['status']);
        }

        return view('library.authors.index', [
            'authors' => $query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        Gate::authorize('create', LibraryAuthor::class);

        return view('library.authors.form', ['author' => new LibraryAuthor(['status' => 'Active'])]);
    }

    public function store(LibraryAuthorRequest $request)
    {
        $author = DB::transaction(function () use ($request) {
            $author = LibraryAuthor::create($request->validated());
            $this->record($request, $author, 'created');

            return $author;
        });

        return redirect()->route('library.authors.show', $author)->with('success', 'Library author added.');
    }

    public function show(LibraryAuthor $author)
    {
        Gate::authorize('view', $author);
        // The Books step will add the Book model and its Eloquent relationship.
        $books = Schema::hasTable('library_books')
            ? DB::table('library_books')->where('author_id', $author->id)->orderBy('title')->orderBy('id')->paginate(15)
            : null;

        return view('library.authors.show', compact('author', 'books'));
    }

    public function edit(LibraryAuthor $author)
    {
        Gate::authorize('update', $author);

        return view('library.authors.form', compact('author'));
    }

    public function update(LibraryAuthorRequest $request, LibraryAuthor $author)
    {
        DB::transaction(function () use ($request, $author) {
            $author = LibraryAuthor::whereKey($author->id)->lockForUpdate()->firstOrFail();
            $author->update($request->validated());
            $this->record($request, $author, 'updated');
        });

        return redirect()->route('library.authors.show', $author)->with('success', 'Library author updated.');
    }

    public function destroy(Request $request, LibraryAuthor $author)
    {
        Gate::authorize('delete', $author);
        $blocked = DB::transaction(function () use ($request, $author) {
            $author = LibraryAuthor::whereKey($author->id)->lockForUpdate()->firstOrFail();
            if (Schema::hasTable('library_books') && DB::table('library_books')->where('author_id', $author->id)->exists()) {
                return true;
            }
            $this->record($request, $author, 'deleted');
            $author->delete();

            return false;
        });

        return redirect()->route('library.authors.index')->with(
            $blocked ? 'error' : 'success',
            $blocked ? 'This author has books. Reassign the books before deleting the author.' : 'Library author deleted.'
        );
    }

    private function record(Request $request, LibraryAuthor $author, string $action): void
    {
        Activity::create(['description' => 'Library author '.$action.': '.$author->name,
            'type' => 'library', 'subject' => 'LibraryAuthor:'.$author->id,
            'user_id' => $request->user()->id, 'created_at' => now()]);
    }
}

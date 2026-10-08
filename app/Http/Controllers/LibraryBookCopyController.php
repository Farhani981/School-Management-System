<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\LibraryBook;
use App\Models\LibraryBookCopy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LibraryBookCopyController extends Controller
{
    public function create(LibraryBook $book)
    {
        Gate::authorize('create', LibraryBookCopy::class);

        return view('library.copies.form', compact('book'));
    }

    public function store(Request $request, LibraryBook $book)
    {
        Gate::authorize('create', LibraryBookCopy::class);
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:100', 'condition' => ['required', Rule::in(LibraryBookCopy::CONDITIONS)],
            'shelf' => 'nullable|string|max:100', 'rack' => 'nullable|string|max:100']);
        DB::transaction(function () use ($request, $book, $data) {
            $book = LibraryBook::whereKey($book->id)->lockForUpdate()->firstOrFail();
            for ($i = 0; $i < $data['quantity']; $i++) {
                $copy = LibraryBookCopy::create(['book_id' => $book->id, 'condition' => $data['condition'],
                    'shelf' => $data['shelf'] ?? null, 'rack' => $data['rack'] ?? $book->rack,
                    'status' => $data['condition'] === 'Damaged' ? 'Damaged' : 'Available']);
                $copy->update(['accession_number' => 'LIB-'.str_pad((string) $copy->id, 6, '0', STR_PAD_LEFT),
                    'barcode' => 'LIB-'.str_pad((string) $copy->id, 6, '0', STR_PAD_LEFT)]);
            }
            Activity::create(['description' => $data['quantity'].' library copies added: '.$book->title, 'type' => 'library',
                'subject' => 'LibraryBook:'.$book->id, 'user_id' => $request->user()->id, 'created_at' => now()]);
        });

        return redirect()->route('library.books.show', $book)->with('success', $data['quantity'].' physical copies added. You can now issue available copies.');
    }
}

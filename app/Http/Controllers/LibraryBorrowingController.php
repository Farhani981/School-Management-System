<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryIssueRequest;
use App\Http\Requests\LibraryReturnRequest;
use App\Models\LibraryBook;
use App\Models\LibraryMember;
use App\Models\LibraryTransaction;
use App\Services\LibraryBorrowingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LibraryBorrowingController extends Controller
{
    private const RELATIONS = ['member.student.schoolClass', 'member.student.section', 'member.teacher.department', 'book', 'copy'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', LibraryTransaction::class);
        $filters = $request->validate(['search' => 'nullable|string|max:150', 'status' => ['nullable', Rule::in(['Issued', 'Overdue', 'Returned', 'Lost', 'All'])],
            'member_id' => 'nullable|integer|exists:library_members,id', 'per_page' => ['nullable', Rule::in([10,25,50])]]);
        $filters['status'] = $filters['status'] ?? 'All';
        $query = LibraryTransaction::with(self::RELATIONS);
        if ($request->filled('search')) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->whereHas('book', fn ($b) => $b->where('title', 'like', $term)->orWhere('isbn', 'like', $term))
                ->orWhereHas('copy', fn ($c) => $c->where('accession_number', 'like', $term)->orWhere('barcode', 'like', $term))
                ->orWhereHas('member', fn ($m) => $m->search($filters['search'])));
        }
        if ($request->filled('member_id')) $query->where('member_id', $filters['member_id']);
        if ($filters['status'] === 'Overdue') {
            $query->whereNull('return_date')->where('due_date', '<', today()->format('Y-m-d'));
        } elseif ($filters['status'] !== 'All') {
            $query->where('status', $filters['status']);
        }

        return view('library.borrowing.index', ['loans' => $query->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 10))->withQueryString(), 'filters' => $filters,
            'issued' => LibraryTransaction::whereNull('return_date')->count(),
            'overdue' => LibraryTransaction::whereNull('return_date')->where('due_date', '<', today()->format('Y-m-d'))->count(),
            'returned' => LibraryTransaction::where('status', 'Returned')->count()]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', LibraryTransaction::class);
        $selection = $request->validate(['member_id' => 'nullable|integer|exists:library_members,id', 'book_id' => 'nullable|integer|exists:library_books,id']);
        $members = LibraryMember::with(['student', 'teacher'])->withCount(['transactions as issued_count' => fn ($q) => $q->whereNull('return_date')])
            ->where('status', 'Active')->where(fn ($m) => $m->where(fn ($s) => $s->where('member_type', 'Student')->whereHas('student', fn ($p) => $p->where('status', 'Active')->whereNull('deleted_at')))
                ->orWhere(fn ($t) => $t->where('member_type', 'Teacher')->whereHas('teacher', fn ($p) => $p->where('status', 'Active')->whereNull('deleted_at'))))->orderBy('member_id')->get();
        $available = fn ($q) => $q->where('status', 'Available')->where('condition', '!=', 'Damaged')->whereDoesntHave('transactions', fn ($loan) => $loan->whereNull('return_date'));
        $books = LibraryBook::where('status', 'Active')->with(['copies' => $available])->orderBy('title')->get();

        return view('library.borrowing.issue', compact('members', 'books', 'selection'));
    }

    public function store(LibraryIssueRequest $request, LibraryBorrowingService $service)
    {
        $loan = $service->issue($request->validated(), $request->user());

        return redirect()->route('library.borrowing.show', $loan)->with('success', 'Book issued. Due date: '.$loan->due_date->format('d M Y').'.');
    }

    public function show(Request $request, LibraryTransaction $transaction)
    {
        Gate::authorize('view', $transaction);
        $layout = $request->user()->hasAnyRole(['Admin', 'Librarian']) ? 'layouts.admin' : ($request->user()->hasRole('Teacher') ? 'layouts.teacher' : 'layouts.portal');

        return view('library.borrowing.show', ['transaction' => $transaction->load([...self::RELATIONS, 'issuer', 'receiver']), 'layout' => $layout]);
    }

    public function returnForm(LibraryTransaction $transaction)
    {
        Gate::authorize('receive', $transaction);
        if ($transaction->return_date) return redirect()->route('library.borrowing.show', $transaction)->with('error', 'This loan has already been closed.');

        return view('library.borrowing.return', ['transaction' => $transaction->load(self::RELATIONS)]);
    }

    public function receive(LibraryReturnRequest $request, LibraryTransaction $transaction, LibraryBorrowingService $service)
    {
        $loan = $service->receive($transaction, $request->validated(), $request->user());

        return redirect()->route('library.borrowing.show', $loan)->with('success', $loan->status === 'Lost' ? 'Copy marked lost. The loan has been closed.' : 'Book return saved. Copy availability has been updated.');
    }
}

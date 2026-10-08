<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\LibraryBook;
use App\Models\LibraryBookCopy;
use App\Models\LibraryMember;
use App\Models\LibraryTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LibraryBorrowingService
{
    public function issue(array $data, User $actor): LibraryTransaction
    {
        return DB::transaction(function () use ($data, $actor) {
            $member = LibraryMember::whereKey($data['member_id'])->lockForUpdate()->firstOrFail();
            $person = $member->member_type === 'Student' ? $member->student()->lockForUpdate()->first() : $member->teacher()->lockForUpdate()->first();
            if ($member->status !== 'Active' || !$person || $person->trashed() || $person->status !== 'Active') {
                throw ValidationException::withMessages(['member_id' => 'This member is inactive or no longer active at school.']);
            }
            if (Carbon::parse($data['issue_date'])->lt($member->registration_date)) {
                throw ValidationException::withMessages(['issue_date' => 'The issue date cannot be before the member registration date.']);
            }
            if (LibraryTransaction::where('member_id', $member->id)->whereNull('return_date')->count() >= $member->borrowing_limit) {
                throw ValidationException::withMessages(['member_id' => 'Borrowing limit reached. Receive a returned book before issuing another.']);
            }
            $book = LibraryBook::whereKey($data['book_id'])->lockForUpdate()->firstOrFail();
            $copy = LibraryBookCopy::whereKey($data['book_copy_id'])->lockForUpdate()->firstOrFail();
            if ($book->status !== 'Active' || $copy->book_id !== $book->id || $copy->status !== 'Available' || $copy->condition === 'Damaged'
                || LibraryTransaction::where('book_copy_id', $copy->id)->whereNull('return_date')->exists()) {
                throw ValidationException::withMessages(['book_copy_id' => 'Choose an available copy of the selected active book.']);
            }
            $due = $data['due_date'] ?? Carbon::parse($data['issue_date'])->addDays((int) config('library.loan_days.'.$member->member_type))->format('Y-m-d');
            $loan = LibraryTransaction::create(array_merge($data, ['due_date' => $due, 'active_copy_id' => $copy->id, 'created_by' => $actor->id, 'status' => 'Issued']));
            $copy->update(['status' => 'Issued']);
            $this->record($actor, $loan, 'issued');

            return $loan;
        }, 3);
    }

    public function receive(LibraryTransaction $transaction, array $data, User $actor): LibraryTransaction
    {
        return DB::transaction(function () use ($transaction, $data, $actor) {
            // Use the same member -> book -> copy order as issuing a book.
            LibraryMember::whereKey($transaction->member_id)->lockForUpdate()->firstOrFail();
            LibraryBook::whereKey($transaction->book_id)->lockForUpdate()->firstOrFail();
            $copy = LibraryBookCopy::whereKey($transaction->book_copy_id)->lockForUpdate()->firstOrFail();
            $loan = LibraryTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($loan->return_date || $loan->status !== 'Issued' || $copy->status !== 'Issued' || $loan->active_copy_id !== $copy->id) {
                throw ValidationException::withMessages(['return_date' => 'This loan has already been closed or its copy is not issued.']);
            }
            $lost = $data['condition'] === 'Lost';
            $loan->update(['return_date' => $data['return_date'], 'return_condition' => $data['condition'], 'return_notes' => $data['return_notes'] ?? null,
                'returned_by' => $actor->id, 'status' => $lost ? 'Lost' : 'Returned', 'active_copy_id' => null]);
            $copy->update(['status' => $lost ? 'Lost' : ($data['condition'] === 'Damaged' ? 'Damaged' : 'Available'),
                'condition' => $lost ? $copy->condition : $data['condition']]);
            $this->record($actor, $loan, $lost ? 'marked lost' : 'returned');

            return $loan;
        }, 3);
    }

    private function record(User $actor, LibraryTransaction $loan, string $action): void
    {
        Activity::create(['description' => 'Library book '.$action.': loan #'.$loan->id, 'type' => 'library',
            'subject' => 'LibraryTransaction:'.$loan->id, 'user_id' => $actor->id, 'created_at' => now()]);
    }
}

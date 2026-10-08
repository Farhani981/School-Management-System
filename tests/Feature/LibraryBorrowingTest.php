<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Department;
use App\Models\Designation;
use App\Models\LibraryAuthor;
use App\Models\LibraryBook;
use App\Models\LibraryBookCopy;
use App\Models\LibraryMember;
use App\Models\LibraryTransaction;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LibraryBorrowingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LibraryBorrowingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-07 12:00:00', 'Asia/Karachi'));
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
    }

    private function member(string $type = 'Student', int $limit = 3): LibraryMember
    {
        $number = LibraryMember::count() + 1;
        $user = User::factory()->create(['role' => $type]);
        if ($type === 'Student') {
            $person = Student::create(['admission_no' => 'ADM-'.$number, 'first_name' => 'Ali', 'last_name' => 'Hassan', 'father_name' => 'Ahmed',
                'date_of_birth' => '2015-01-10', 'gender' => 'Male', 'phone' => '03001234567', 'address' => 'School Road', 'admission_date' => '2026-09-01',
                'roll_number' => (string) $number, 'status' => 'Active', 'user_id' => $user->id]);
        } else {
            $person = Teacher::create(['employee_id' => 'TCH-'.$number, 'name' => 'Ayesha Khan', 'cnic' => '12345-1234567-'.$number, 'gender' => 'Female', 'phone' => '03007654321',
                'department_id' => Department::firstOrCreate(['name' => 'Science'])->id, 'designation_id' => Designation::firstOrCreate(['name' => 'Lecturer'])->id,
                'joining_date' => '2026-01-01', 'status' => 'Active', 'user_id' => $user->id]);
        }
        $member = LibraryMember::create(['member_type' => $type, $type === 'Student' ? 'student_id' : 'teacher_id' => $person->id,
            'registration_date' => '2026-10-01', 'borrowing_limit' => $limit, 'status' => 'Active']);
        $member->member_id = ($type === 'Student' ? 'STU' : 'TCH').'-LIB-'.str_pad((string) $member->id, 5, '0', STR_PAD_LEFT);
        $member->save();

        return $member;
    }

    private function book(): LibraryBook
    {
        return LibraryBook::create(['title' => 'Science Basics '.(LibraryBook::count() + 1), 'author_id' => LibraryAuthor::firstOrCreate(['name' => 'Writer'], ['status' => 'Active'])->id,
            'rack' => 'A1', 'status' => 'Active']);
    }

    private function copy(?LibraryBook $book = null): LibraryBookCopy
    {
        $number = LibraryBookCopy::count() + 1;

        return LibraryBookCopy::create(['book_id' => ($book ?? $this->book())->id, 'accession_number' => 'COPY-'.$number,
            'barcode' => 'BARCODE-'.$number, 'condition' => 'Good', 'status' => 'Available']);
    }

    private function data(LibraryMember $member, LibraryBookCopy $copy, array $overrides = []): array
    {
        return array_merge(['member_id' => $member->id, 'book_id' => $copy->book_id, 'book_copy_id' => $copy->id, 'issue_date' => '2026-10-07', 'notes' => 'Study reading'], $overrides);
    }

    public function test_copies_are_registered_with_unique_numbers_and_real_inventory_counts(): void
    {
        $book = $this->book();
        $this->get(route('library.copies.create', $book))->assertOk()->assertSee('Number of Copies');
        $this->post(route('library.copies.store', $book), ['quantity' => 3, 'condition' => 'Good'])->assertSessionHasNoErrors();
        $this->post(route('library.copies.store', $book), ['quantity' => 1, 'condition' => 'Damaged'])->assertSessionHasNoErrors();
        $copies = LibraryBookCopy::all();
        $this->assertCount(4, $copies);
        $this->assertCount(4, $copies->pluck('accession_number')->unique());
        $this->assertCount(4, $copies->pluck('barcode')->unique());
        $this->assertSame('A1', $copies->first()->rack);
        $counts = LibraryBook::withInventoryCounts()->findOrFail($book->id);
        $this->assertEquals(3, $counts->available_count);
        $this->assertEquals(1, $counts->damaged_count);
        $this->get(route('library.books.show', $book))->assertOk()->assertSee('Add Copies')->assertSee('Issue Book')->assertSee($copies->first()->accession_number);
        $this->post(route('library.copies.store', $book), ['quantity' => 101, 'condition' => 'bad'])->assertSessionHasErrors(['quantity', 'condition']);
        $this->assertDatabaseCount('library_book_copies', 4);
        $this->delete(route('library.books.destroy', $book))->assertSessionHas('error');
    }

    public function test_issue_and_return_change_availability_and_keep_history_and_actor(): void
    {
        $actor = auth()->user();
        $member = $this->member();
        $copy = $this->copy();
        $this->get(route('library.borrowing.create'))->assertOk()->assertSee('Ali Hassan')->assertSee($copy->accession_number);
        $this->post(route('library.borrowing.store'), $this->data($member, $copy))->assertSessionHasNoErrors();
        $loan = LibraryTransaction::firstOrFail();
        $this->assertSame('2026-10-21', $loan->due_date->format('Y-m-d'));
        $this->assertSame('Issued', $copy->fresh()->status);
        $this->assertSame($copy->id, $loan->active_copy_id);
        $this->assertSame($actor->id, $loan->created_by);
        $counts = LibraryBook::withInventoryCounts()->findOrFail($copy->book_id);
        $this->assertEquals(0, $counts->available_count);
        $this->assertEquals(1, $counts->issued_count);
        $this->get(route('library.borrowing.create'))->assertOk()->assertDontSee($copy->accession_number)->assertSee('No available copies');
        $this->get(route('library.borrowing.index'))->assertOk()->assertSee('Receive Return')->assertSee($copy->accession_number);
        $this->get(route('library.members.show', $member))->assertOk()->assertSee('Borrowing History')->assertSee('Loan Details');
        $this->get(route('library.borrowing.return', $loan))->assertOk()->assertSee('Save Return');
        $this->post(route('library.borrowing.receive', $loan), ['return_date' => '2026-10-07', 'condition' => 'Fair', 'return_notes' => 'Received by desk'])->assertSessionHasNoErrors();
        $this->assertSame('Returned', $loan->fresh()->status);
        $this->assertNull($loan->fresh()->active_copy_id);
        $this->assertSame($actor->id, $loan->fresh()->returned_by);
        $this->assertSame('Available', $copy->fresh()->status);
        $this->assertSame('Fair', $copy->fresh()->condition);
        $this->get(route('library.borrowing.index'))->assertOk()
            ->assertViewHas('filters', fn ($filters) => $filters['status'] === 'All')
            ->assertViewHas('loans', fn ($loans) => $loans->contains('id', $loan->id))
            ->assertSee($copy->accession_number)->assertSee('Returned')->assertDontSee('Receive Return');
        $this->get(route('library.borrowing.index', ['status' => 'Issued']))->assertOk()
            ->assertViewHas('loans', fn ($loans) => $loans->isEmpty());
        $this->get(route('library.borrowing.show', $loan))->assertOk()->assertSee('Received by desk')->assertDontSee('Receive Return');
        $this->assertDatabaseHas('activities', ['user_id' => $actor->id, 'description' => 'Library book returned: loan #'.$loan->id]);
        $this->post(route('library.borrowing.store'), $this->data($member, $copy))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('library_transactions', 2);
        // Replaying an old return must not release a copy that has been reissued.
        $this->post(route('library.borrowing.receive', $loan), ['return_date' => '2026-10-07', 'condition' => 'Good'])->assertSessionHasErrors('return_date');
        $this->assertSame('Issued', $copy->fresh()->status);
        $this->assertSame('Returned', $loan->fresh()->status);
    }

    public function test_limits_blocked_members_wrong_books_and_unavailable_copies_cannot_be_issued(): void
    {
        $member = $this->member('Student', 1);
        $copy = $this->copy();
        $other = $this->copy($copy->book);
        $this->post(route('library.borrowing.store'), $this->data($member, $copy))->assertSessionHasNoErrors();
        $this->post(route('library.borrowing.store'), $this->data($member, $other))->assertSessionHasErrors('member_id');
        $member->update(['borrowing_limit' => 5]);
        $this->post(route('library.borrowing.store'), $this->data($member, $copy))->assertSessionHasErrors('book_copy_id');
        $this->post(route('library.borrowing.store'), $this->data($member, $other, ['book_id' => $this->book()->id]))->assertSessionHasErrors('book_copy_id');
        $member->update(['status' => 'Suspended']);
        $this->post(route('library.borrowing.store'), $this->data($member, $other))->assertSessionHasErrors('member_id');
        $member->update(['status' => 'Active']);
        $member->student->update(['status' => 'Inactive']);
        $this->post(route('library.borrowing.store'), $this->data($member, $other))->assertSessionHasErrors('member_id');
        $member->student->update(['status' => 'Active']);
        $other->update(['status' => 'Damaged', 'condition' => 'Damaged']);
        $this->post(route('library.borrowing.store'), $this->data($member, $other))->assertSessionHasErrors('book_copy_id');
        $this->assertDatabaseCount('library_transactions', 1);
        $this->assertSame('Damaged', $other->fresh()->status);
        // A suspended membership can still return its existing loan.
        $member->update(['status' => 'Suspended']);
        $loan = LibraryTransaction::firstOrFail();
        $this->post(route('library.borrowing.receive', $loan), ['return_date' => '2026-10-07', 'condition' => 'Good'])->assertSessionHasNoErrors();
    }

    public function test_dates_teacher_loan_period_overdue_search_and_damaged_or_lost_returns(): void
    {
        $member = $this->member('Teacher', 5);
        $copy = $this->copy();
        $this->post(route('library.borrowing.store'), $this->data($member, $copy, ['issue_date' => '2026-10-08']))->assertSessionHasErrors('issue_date');
        $this->post(route('library.borrowing.store'), $this->data($member, $copy, ['due_date' => '2026-10-06']))->assertSessionHasErrors('due_date');
        $this->post(route('library.borrowing.store'), $this->data($member, $copy, ['issue_date' => '2026-09-30']))->assertSessionHasErrors('issue_date');
        $this->post(route('library.borrowing.store'), $this->data($member, $copy))->assertSessionHasNoErrors();
        $loan = LibraryTransaction::firstOrFail();
        $this->assertSame('2026-11-06', $loan->due_date->format('Y-m-d'));
        $this->post(route('library.borrowing.receive', $loan), ['return_date' => '2026-10-08', 'condition' => 'Good'])->assertSessionHasErrors('return_date');
        $this->post(route('library.borrowing.receive', $loan), ['return_date' => '2026-10-07', 'condition' => 'Damaged'])->assertSessionHasNoErrors();
        $this->assertSame('Damaged', $copy->fresh()->status);
        $other = $this->copy();
        $this->post(route('library.borrowing.store'), $this->data($member, $other, ['issue_date' => '2026-10-01', 'due_date' => '2026-10-04']))->assertSessionHasNoErrors();
        $overdue = LibraryTransaction::latest('id')->firstOrFail();
        $this->assertSame('Overdue', $overdue->display_status);
        $this->assertSame(3, $overdue->days_late);
        foreach ([$member->name, $member->member_id, $other->accession_number, $other->barcode, $other->book->title] as $search) {
            $this->get(route('library.borrowing.index', ['search' => $search, 'status' => 'Overdue']))->assertOk()->assertViewHas('loans', fn ($loans) => $loans->total() === 1);
        }
        $this->post(route('library.borrowing.receive', $overdue), ['return_date' => '2026-09-30', 'condition' => 'Lost'])->assertSessionHasErrors('return_date');
        $this->post(route('library.borrowing.receive', $overdue), ['return_date' => '2026-10-06', 'condition' => 'Lost'])->assertSessionHasNoErrors();
        $this->assertSame('Lost', $other->fresh()->status);
        $this->assertSame('Lost', $overdue->fresh()->status);
        $this->assertSame(2, $overdue->fresh()->days_late);
        $this->get(route('library.borrowing.index', ['status' => 'Lost']))->assertOk()->assertSee($other->accession_number)->assertDontSee($copy->accession_number);
    }

    public function test_librarians_operate_loans_and_teachers_and_students_only_read_their_own_records(): void
    {
        $student = $this->member();
        $teacher = $this->member('Teacher');
        $copy = $this->copy();
        $librarian = User::factory()->create(['role' => 'Librarian']);
        $librarian->assignRole(Role::findOrCreate('Librarian', 'web'));
        $this->actingAs($librarian)->get(route('library.borrowing.index'))->assertOk();
        $this->post(route('library.copies.store', $copy->book), ['quantity' => 1, 'condition' => 'Good'])->assertSessionHasNoErrors();
        $this->post(route('library.borrowing.store'), $this->data($student, $copy))->assertSessionHasNoErrors();
        $loan = LibraryTransaction::firstOrFail();
        $this->actingAs($student->student->user)->get(route('library.borrowing.show', $loan))->assertOk()->assertDontSee('Receive Return');
        $this->get(route('library.members.show', $student))->assertOk()->assertSee($copy->accession_number)->assertDontSee('Receive Return');
        $this->actingAs($teacher->teacher->user)->get(route('library.borrowing.show', $loan))->assertForbidden();
        foreach ([$student->student->user, $teacher->teacher->user] as $user) {
            $this->actingAs($user)->get(route('library.borrowing.index'))->assertForbidden();
            $this->get(route('library.borrowing.create'))->assertForbidden();
            $this->post(route('library.borrowing.store'), $this->data($student, $copy))->assertForbidden();
            $this->get(route('library.borrowing.return', $loan))->assertForbidden();
            $this->post(route('library.borrowing.receive', $loan), ['return_date' => '2026-10-07', 'condition' => 'Good'])->assertForbidden();
            $this->post(route('library.copies.store', $copy->book), ['quantity' => 1, 'condition' => 'Good'])->assertForbidden();
        }
        $this->actingAs($librarian)->post(route('library.borrowing.receive', $loan), ['return_date' => '2026-10-07', 'condition' => 'Good'])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get(route('library.borrowing.index'))->assertRedirect(route('login'));
    }

    public function test_issue_rolls_back_if_audit_write_fails_and_database_blocks_duplicate_open_loans(): void
    {
        $member = $this->member();
        $copy = $this->copy();
        $service = app(LibraryBorrowingService::class);
        $dispatcher = Activity::getEventDispatcher();
        Activity::setEventDispatcher(clone $dispatcher);
        Activity::creating(fn () => throw new \RuntimeException('Audit storage unavailable'));
        try {
            $service->issue($this->data($member, $copy), auth()->user());
            $this->fail('The injected audit failure must abort the issue.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Audit storage unavailable', $error->getMessage());
        } finally {
            Activity::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('library_transactions', 0);
        $this->assertSame('Available', $copy->fresh()->status);
        $loan = $service->issue($this->data($member, $copy), auth()->user());
        try {
            LibraryTransaction::create($loan->only(['member_id', 'book_id', 'book_copy_id', 'active_copy_id', 'issue_date', 'due_date', 'created_by', 'status']));
            $this->fail('A physical copy must not have two open loans.');
        } catch (QueryException) {
            $this->assertDatabaseCount('library_transactions', 1);
        }
    }
}

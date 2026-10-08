<?php

namespace Tests\Feature;

use App\Models\LibraryAuthor;
use App\Models\LibraryBook;
use App\Models\LibraryCategory;
use App\Models\User;
use App\Models\LibraryBookCopy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LibraryBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_cover_accepts_images_up_to_ten_megabytes(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
        $cover = UploadedFile::fake()->image('cover.jpg')->size(10240);
        $this->post(route('library.books.store'), $this->data() + ['cover' => $cover])->assertSessionHasNoErrors();
        $book = LibraryBook::firstOrFail();
        Storage::disk('public')->assertExists($book->cover);
    }

    public function test_book_cover_rejects_images_above_ten_megabytes_with_a_clear_message(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
        $cover = UploadedFile::fake()->image('cover.jpg')->size(10241);
        $this->post(route('library.books.store'), $this->data() + ['cover' => $cover])
            ->assertSessionHasErrors(['cover' => 'The book cover must be 10 MB or smaller.']);
        $this->assertDatabaseCount('library_books', 0);
    }

    private function data(): array
    {
        return ['title' => 'Computer Fundamentals', 'author_id' => LibraryAuthor::firstOrCreate(['name' => 'Sample Writer'], ['status' => 'Active'])->id, 'publisher_name' => 'Oxford', 'isbn' => '978-1234567890',
            'rack' => '1234', 'status' => 'Active'];
    }

    public function test_existing_books_keep_their_data_after_category_setup_is_removed(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
        $category = LibraryCategory::create(['name' => 'Legacy Science', 'status' => 'Active']);
        $book = LibraryBook::create($this->data() + ['category_id' => $category->id]);

        $this->get('/library/categories')->assertNotFound();
        $this->get('/library/categories/create')->assertNotFound();
        $this->post('/library/categories', ['name' => 'New Category', 'status' => 'Active'])->assertNotFound();
        $this->get(route('library.books.index'))->assertOk()->assertSee($book->title)->assertDontSee('Legacy Science')->assertDontSee('Categories');
        $this->get(route('library.books.show', $book))->assertOk()->assertSee($book->title)->assertDontSee('Legacy Science');
        $this->get(route('library.books.edit', $book))->assertOk()->assertDontSee('name="category_id"', false)->assertDontSee('button-primary" disabled', false);
        $this->put(route('library.books.update', $book), array_replace($this->data(), ['title' => 'Updated Book', 'category_id' => 999999]))->assertSessionHasNoErrors();
        $this->assertEquals('Updated Book', $book->fresh()->title);
        $this->assertEquals($category->id, $book->fresh()->category_id);
        $this->assertDatabaseHas('library_categories', ['id' => $category->id, 'name' => 'Legacy Science']);
    }

    public function test_book_form_accepts_a_typed_author_when_the_directory_is_empty_and_reuses_the_name(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'Admin']));
        $this->assertDatabaseCount('library_categories',0);
        $this->assertDatabaseCount('library_authors',0);
        $this->get(route('library.books.create'))->assertOk()->assertSee('name="author_name"',false)->assertSee('Enter author name')->assertDontSee('Select Author')->assertDontSee('Subject / Category')->assertDontSee('name="category_id"',false);
        $data=['title'=>'Science Basics','author_name'=>'  Jane   Cooper  ','status'=>'Active'];
        $this->post(route('library.books.store'),$data)->assertSessionHasNoErrors();
        $book=LibraryBook::firstOrFail();
        $this->assertNull($book->category_id);
        $this->assertDatabaseCount('library_categories',0);
        $this->assertEquals('Jane Cooper',$book->author->name);
        $this->get(route('library.books.show',$book))->assertOk()->assertSee('Jane Cooper');
        $this->post(route('library.books.store'),array_replace($data,['title'=>'Science Workbook','author_name'=>'jane cooper']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('library_authors',1);
        $this->get(route('library.books.edit',$book))->assertOk()->assertSee('value="Jane Cooper"',false);
        $this->put(route('library.books.update',$book),array_replace($data,['author_name'=>'New Writer']))->assertSessionHasNoErrors();
        $this->assertEquals('New Writer',$book->fresh()->author->name);
        $this->post(route('library.books.store'),array_replace($data,['author_name'=>'   ']))->assertSessionHasErrors('author_name');
        $this->post(route('library.books.store'),array_replace($data,['author_name'=>str_repeat('a',151)]))->assertSessionHasErrors('author_name');
        $this->assertDatabaseCount('library_authors',2);
    }

    public function test_editing_preserves_the_current_author_when_two_authors_share_the_name(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'Admin']));
        $data=$this->data();
        $otherAuthor=LibraryAuthor::create(['name'=>'Sample Writer','country'=>'Pakistan','status'=>'Active']);
        $data['author_id']=$otherAuthor->id;
        $this->post(route('library.books.store'),$data)->assertSessionHasNoErrors();
        $book=LibraryBook::firstOrFail();
        unset($data['author_id']);
        $data['author_name']='Sample Writer';
        $this->put(route('library.books.update',$book),$data)->assertSessionHasNoErrors();
        $this->assertEquals($otherAuthor->id,$book->fresh()->author_id);
    }

    public function test_admin_saves_books_sees_details_searches_filters_exports_edits_and_deletes(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin);
        $this->get(route('library.books.create'))->assertOk()->assertSee('Save Book')->assertDontSee('name="price"', false)->assertDontSee('name="purchase_date"', false)->assertDontSee('name="supplier"', false);
        $this->post(route('library.books.store'), $this->data())->assertSessionHasNoErrors();
        $book = LibraryBook::firstOrFail();
        $this->assertEquals('0.00', $book->price);
        $this->get(route('library.index'))->assertRedirect(route('library.books.index'));
        $this->get(route('library.books.index'))->assertOk()->assertSee('Computer Fundamentals')->assertSee('Book Details')->assertSee('Members List')->assertSee('Issue Return');
        $response = $this->get(route('library.books.index', ['search' => 'Oxford', 'author_id' => $book->author_id, 'publisher_id' => $book->publisher_id, 'per_page' => 25]))->assertOk();
        $this->assertEquals(1, $response->viewData('books')->total());
        $this->assertEquals(0, $response->viewData('books')->first()->copies_count);
        $this->get(route('library.books.index', ['availability' => 'available']))->assertOk()->assertDontSee('Computer Fundamentals');
        $this->get(route('library.books.show', $book))->assertOk()->assertSee('Book Details')->assertSee('Oxford')->assertSee('1234')->assertSee('Physical Copies')->assertDontSee('Purchase Date')->assertDontSee('Price');
        $export=$this->get(route('library.books.index', ['export' => 'csv', 'book_ids' => [$book->id]]))->assertDownload('library-books.csv');
        $this->assertStringContainsString('Computer Fundamentals',$export->streamedContent());
        $this->assertStringNotContainsString('Subject',$export->streamedContent());
        $this->assertStringNotContainsString('Price',$export->streamedContent());
        $this->assertStringNotContainsString('Purchase Date',$export->streamedContent());
        // Editing the simple form preserves any metadata saved before this change.
        $book->update(['price'=>375,'purchase_date'=>'2026-10-07']);
        $this->put(route('library.books.update', $book), array_replace($this->data(), ['title' => 'Computer Science Basics', 'status' => 'Inactive']))->assertSessionHasNoErrors();
        $this->assertEquals('375.00',$book->fresh()->price);
        $this->get(route('library.books.index', ['status' => 'Active']))->assertOk()->assertDontSee('Computer Science Basics');
        $this->delete(route('library.books.destroy', $book))->assertSessionHas('success');
        $this->assertDatabaseMissing('library_books', ['id' => $book->id]);
        $this->assertDatabaseHas('activities', ['user_id' => $admin->id, 'description' => 'Library book deleted: Computer Science Basics']);
    }

    public function test_validation_and_cover_upload_work(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
        $data = $this->data();
        $this->post(route('library.books.store'), $data + ['cover' => UploadedFile::fake()->image('cover.jpg')])->assertSessionHasNoErrors();
        $book = LibraryBook::firstOrFail();
        Storage::disk('public')->assertExists($book->cover);
        $this->post(route('library.books.store'), $data)->assertSessionHasErrors('isbn');
        $this->post(route('library.books.store'), array_replace($data, ['title' => '', 'author_id' => 999999, 'rack' => str_repeat('a',101)]))->assertSessionHasErrors(['title', 'author_id', 'rack']);
        $this->post(route('library.books.store'), array_replace($data, ['isbn' => null, 'cover' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')]))->assertSessionHasErrors('cover');
        $this->assertDatabaseCount('library_books', 1);
    }

    public function test_physical_copy_counts_search_and_deletion_guard_use_inventory(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
        $this->post(route('library.books.store'), $this->data())->assertSessionHasNoErrors();
        $book = LibraryBook::firstOrFail();
        LibraryBookCopy::create(['book_id' => $book->id, 'accession_number' => 'LIB-001', 'barcode' => 'SCAN-001', 'condition' => 'Good', 'status' => 'Available']);
        LibraryBookCopy::create(['book_id' => $book->id, 'accession_number' => 'LIB-002', 'barcode' => 'SCAN-002', 'condition' => 'Good', 'status' => 'Issued']);
        $response = $this->get(route('library.books.index', ['search' => 'SCAN-001', 'availability' => 'available']))->assertOk();
        $this->assertEquals(2, $response->viewData('books')->first()->copies_count);
        $this->assertEquals(1, $response->viewData('books')->first()->available_count);
        $this->get(route('library.books.show', $book))->assertOk()->assertSee('LIB-002');
        $this->delete(route('library.books.destroy', $book))->assertSessionHas('error');
        $this->assertDatabaseHas('library_books', ['id' => $book->id]);
    }

    public function test_student_teacher_and_librarian_read_catalog_but_cannot_modify_it(): void
    {
        $book = LibraryBook::create($this->data());
        $this->get(route('library.books.index'))->assertRedirect(route('login'));
        foreach (['Student', 'Teacher', 'Librarian'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            if ($role === 'Librarian') {
                $user->assignRole(Role::findOrCreate('Librarian', 'web'));
            }
            $this->actingAs($user)->get(route('library.books.index'))->assertOk()->assertSee($book->title)->assertDontSee('+ Add Book');
            $this->get(route('library.books.show', $book))->assertOk()->assertDontSee('Edit Book');
            $this->get(route('library.books.create'))->assertForbidden();
            $this->get(route('library.books.edit', $book))->assertForbidden();
            $this->post(route('library.books.store'), $this->data())->assertForbidden();
            $this->put(route('library.books.update', $book), $this->data())->assertForbidden();
            $this->delete(route('library.books.destroy', $book))->assertForbidden();
        }
    }

    public function test_author_setup_is_usable_and_authors_with_books_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
        $this->get(route('library.authors.create'))->assertOk()->assertSee('Save Author');
        $this->post(route('library.authors.store'), ['name' => 'New Writer', 'email' => 'writer@example.com', 'status' => 'Active'])->assertSessionHasNoErrors();
        $author = LibraryAuthor::firstOrFail();
        $this->get(route('library.authors.index'))->assertOk()->assertSee('New Writer');
        $this->get(route('library.authors.show', $author))->assertOk()->assertSee('writer@example.com');
        $this->post(route('library.authors.store'), ['name' => '', 'email' => 'invalid', 'status' => 'Unknown'])->assertSessionHasErrors(['name', 'email', 'status']);
        $data = $this->data();
        $data['author_id'] = $author->id;
        $this->post(route('library.books.store'), $data)->assertSessionHasNoErrors();
        $this->get(route('library.authors.show',$author))->assertOk()->assertSee('Computer Fundamentals');
        $this->delete(route('library.authors.destroy',$author))->assertSessionHas('error');
    }
}

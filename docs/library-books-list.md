# Library: Books List and Book Details

The library uses a simple borrowing catalog. Categories have been removed from navigation, routes, Add/Edit Book, list columns, filters, details and CSV exports. Books can be saved without creating any categories. The category controller, request, policy, views and obsolete CRUD tests have been removed.

The new migration makes library_books.category_id nullable. Historical category records and existing book associations are retained, and editing a book preserves them. The original migrations and legacy model relationships remain for database compatibility. Rolling back the nullable migration is blocked if uncategorized books exist, so rollback cannot discard books or assign invented categories.

## Current behavior

- /library opens Books List. Admin can add, edit and delete titles; Admin, Librarian, Teacher and Student can read the catalog.
- Book fields: title, typed author name, optional publisher, ISBN, rack, status, optional cover and description.
- Authors can be typed directly. Saving creates a new author or reuses a matching name in the same transaction. Editing an unchanged name preserves the original author ID even when names are duplicated.
- Publisher names can be selected or typed; new publishers are created when saving.
- Search matches title, ISBN, author and publisher; accession/barcode search activates when physical copies exist.
- Author, publisher, status and availability filters, pagination and selected-row CSV export work.
- The list shows book name, publisher, author, catalog number, rack, total copies, available copies and issued copies.
- Book Details shows basic metadata, description and inventory totals. Action menus close on outside click, link selection, Escape or opening another menu.
- Copies, availability and issued quantities come from physical inventory. Book Details now has Add Copies and Issue Book. Members List has Member Details in each row's Action menu. Issue Return now tracks loans and returns; see docs/library-issue-return.md.
- Price, purchase date and purchasing fields are absent from the UI and export. Stored legacy metadata survives edits.
- Books with copies, borrowing history or reservations cannot be deleted. Authors with books cannot be deleted. Changes use existing Activity logging and authorization.

## Validation

From the project root:

~~~powershell
php artisan migrate
php artisan route:clear
php artisan view:clear
php artisan test --filter=LibraryBookTest
npm.cmd run build
~~~

The category removal migration is database/migrations/2026_10_07_000700_make_library_book_category_optional.php. Tests cover saving with empty category and author directories, legacy book preservation, retired category URLs, CRUD, CSV, validation, uploads, role permissions and inventory deletion guards.

For a manual check, open Library > Books List > Add Book, type a title and author and save. Book Details should open. Edit and save again, then try search, filters and CSV. The category field and Categories link should be absent. Verify that Action > Book Details opens the selected title and the menu closes when clicking outside.

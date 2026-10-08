# Library Issue / Return

The Library dropdown now has Books List, Members List and Issue Return. Member Details stays in the member row Action menu. Issue Return supports issuing available physical copies, receiving returns, viewing overdue loans and keeping borrowing history. It uses the existing theme, user roles and Activity logs.

## Using it

1. Open Library > Books List > Book Details > Add Copies. Enter how many physical books you actually have, condition and optional shelf/rack. Each copy receives its own accession number and barcode. Damaged copies are unavailable.
2. Add the borrower through Members List if they are not already registered. Members reuse existing student/teacher records.
3. Open Issue Return > Issue Book. Select the active member, search the book dropdown by title or ISBN and choose a result, then select an available copy. The dropdown supports arrow keys, Enter, Escape and closing on outside click. An unmatched typed name cannot be submitted as a book. Due date is suggested automatically; you can adjust it. Save.
4. The copy becomes Issued. Book availability counts change, and Member Details shows the loan in Borrowing History.
5. Find the loan by member name/ID, book title/ISBN, accession number or barcode. Use the status filter for Currently Issued, Overdue, Returned, Lost or All records.
6. Open Action > Receive Return. Enter the return date and condition, then save. New/Good/Fair copies become Available. Damaged copies stay Damaged. Lost closes the loan without marking the copy available.

Dates use the app's Asia/Karachi timezone. The centralized defaults in config/library.php are Student: 14 days and Teacher: 30 days. The member's own borrowing_limit controls the maximum open loans. Due today becomes overdue tomorrow. Days Late uses the actual return/closure date once closed. This step records late days and damaged/lost outcomes; it does not create fines or replacement charges. Fine payments and renewals remain separate later steps.

## Safety and permissions

Admin and Librarian can add copies, issue and receive returns. Students and teachers can view only their own loans through the linked membership. List, create, return and copy creation routes reject unauthorized requests server-side.

Inactive, suspended, archived or inactive-school borrowers cannot receive new issues. Their existing books can still be returned. Borrowing limits include overdue open loans. The issue date cannot precede membership registration or be in the future. Due dates cannot precede issue dates; returns cannot precede issuing or be in the future.

Issuing and returning use transactions and consistent member/book/copy locks. Issuing serializes requests for the same member before counting open loans. A unique nullable active_copy_id on library_transactions enforces one open loan per copy. Returning clears that key. Replaying an old return cannot release a copy that was reissued. Failures roll back copy status, loan and audit changes. Historical loans are retained, and foreign keys prevent deleting related books, copies or members.

## Files and validation

The additive migration database/migrations/2026_10_07_000900_create_library_borrowing_tables.php creates library_book_copies and library_transactions. New models are LibraryBookCopy and LibraryTransaction. LibraryBorrowingService owns the atomic issue/return operations. LibraryBorrowingController, LibraryBookCopyController, policies and Form Requests handle the authorized routes and validation. Screens live under resources/views/library/borrowing and resources/views/library/copies. Existing Book Details exposes Add Copies; Member Details shows history. No book, student or teacher records are replaced.

~~~powershell
php artisan migrate --path=database/migrations/2026_10_07_000900_create_library_borrowing_tables.php
php artisan route:clear
php artisan view:clear
php artisan test --filter=Library
npm.cmd run build
~~~

Tests cover stock counts, unique copy identifiers, issue/return transitions, default due dates and overrides, duplicate issues/returns, member limits/status, mismatched book copies, overdue search, damaged/lost closure, private records, Librarian operations, audit failure rollback and database uniqueness. Temporary browser previews also run an issue and return on the local database and roll back all preview data.

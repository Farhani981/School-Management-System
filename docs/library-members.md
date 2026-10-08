# Library Members

Open Library > Members List. Click Add Member, choose Student or Teacher and select an existing school record. Set registration date, borrowing limit and status, then save. The generated member ID appears on Member Details. No new student, teacher or user is created.

Member Details is in each row's three-dot Action menu. It is no longer a separate sidebar item. The same menu has Edit Member. Editing can change registration date, status and borrowing limit, while the member ID and linked borrower remain fixed. Set status to Inactive or Suspended to stop a membership rather than deleting it.

The directory supports search by member ID, person name, admission/employee number or phone, type/status filters and 10/25/50-row pagination. Names, class, section, department and contacts come from the existing school records. Archived school records remain readable on existing memberships but cannot be registered again.

Admin and Librarian can manage memberships. Teachers and students cannot browse the member directory or change memberships; the show route authorizes only their own linked member record. Backend policy checks enforce these restrictions. Duplicate, inactive, archived or mismatched borrower selections are rejected. Unique foreign keys and a transaction with a locked school record prevent duplicate memberships.

The default borrowing limits are centralized in config/library.php (Student: 3, Teacher: 5) and can be changed per member. Issue/Return now enforces those limits and Member Details shows Borrowing History; see docs/library-issue-return.md.

Files: database/migrations/2026_10_07_000800_create_library_members_table.php; app/Models/LibraryMember.php; app/Policies/LibraryMemberPolicy.php; app/Http/Requests/LibraryMemberRequest.php; app/Http/Controllers/LibraryMemberController.php; resources/views/library/members/{index,form,show}.blade.php. The existing sidebar, library CSS and app JavaScript provide navigation, table styling and member type selection. Existing book operations and dropdown dismissal remain in place.

Validation commands from the project root:

~~~powershell
php artisan migrate --path=database/migrations/2026_10_07_000800_create_library_members_table.php
php artisan route:clear
php artisan view:clear
php artisan test --filter=Library
npm.cmd run build
~~~

Tests cover student/teacher membership creation without duplicate school records, details links, search/filters, duplicate and inactive prevention, immutable borrower identity, custom limit editing, Activity logs, Librarian writes and private record authorization. Preview scripts reuse existing school people and roll back temporary memberships.

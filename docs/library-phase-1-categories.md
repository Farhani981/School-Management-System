# Library foundation: Step 1 — Categories

Update: Categories have now been removed at the user's request. See docs/library-books-list.md for the current catalog. Category routes, screens and book requirements are retired; historical records remain intact. The remainder describes the original step.

## Existing project audit

The project uses Laravel 12, PHP 8.2+, MySQL, Blade, Vite, and Spatie Laravel Permission. Before this step, Library was a placeholder button in `resources/views/components/sidebar.blade.php`. There were no library models, migrations, routes, controllers, or tables in the configured school database.

Reuse these existing tables and models:

| Existing table | Model / purpose |
| --- | --- |
| users | User; authentication, roles, notifications |
| students | Student; future student membership, existing user_id |
| teachers | Teacher; future teacher membership, existing user relationship |
| school_classes | SchoolClass |
| school_sections | SchoolSection |
| academic_sessions | AcademicSession |
| roles, permissions, model_has_roles and permission pivots | Existing Spatie authorization |
| notifications | Existing database notifications |
| activities | Activity; category action history including actor and category ID |

The database currently has Admin, Principal and Teacher roles. User already supports Student and Parent role synchronization; these need not be duplicated. Librarian will use the same Spatie role infrastructure when operational library access is introduced. This step does not create staff accounts or grant anyone new permissions. The category policy already permits a Spatie Librarian to read the category directory, while only Admin can create, update or delete categories. Principal does not receive library management rights implicitly.

Reuse `resources/views/layouts/admin.blade.php`, `resources/views/components/sidebar.blade.php`, the existing topbar, buttons, forms, tables, validation feedback and responsive CSS. Public/private Laravel storage disks already exist for later book cover uploads. DomPDF and Laravel Excel already exist for later reports. No new auth, layout, student, teacher, class, section, session, notification or PDF infrastructure is needed.

New foundation data will be library_categories, library_authors, library_publishers and library_books. Only library_categories is implemented now. Physical copies, members, issue/return, fines and reservations belong to later phases.

## What was implemented

Category list, add/edit forms, deletion, search by name/description, Active/Inactive filtering, pagination, backend validation, backend authorization and activity recording. Names are trimmed and repeated whitespace is collapsed; category names are unique. Book counts are zero while Books is unimplemented. Once library_books exists, counts become live and deletion refuses categories containing books. The Books step must provide the restrictive category foreign key and the Book model relationship.

Files already contain the complete working code; you do not need to paste it again:

| File | Where the code belongs |
| --- | --- |
| database/migrations/2026_10_07_000400_create_library_categories_table.php | up() creates the table; down() drops only this table |
| app/Models/LibraryCategory.php | Category fields |
| app/Policies/LibraryCategoryPolicy.php | Read/manage authorization, discovered automatically by Laravel |
| app/Http/Requests/LibraryCategoryRequest.php | Input normalization, validation and write authorization |
| app/Http/Controllers/LibraryCategoryController.php | List/create/edit/delete, counts and existing Activity logging |
| resources/views/library/categories/index.blade.php | Category directory using the existing admin layout |
| resources/views/library/categories/form.blade.php | Add/edit form using the existing form styles |
| routes/web.php | New authenticated /library route group, existing routes retained |
| resources/views/components/sidebar.blade.php | Library → Categories submenu, visible according to policy |
| tests/Feature/LibraryCategoryTest.php | CRUD, validation, attached-book deletion guard, role checks |

The route file was modified to make category pages accessible, and the sidebar to replace the Library placeholder with a real submenu. Other routes and the shared layout/theme are retained. No dependency or asset changes are required.

## Commands

Run commands from `C:\school management system`:

```powershell
php artisan migrate --path=database/migrations/2026_10_07_000400_create_library_categories_table.php
php artisan route:clear
php artisan view:clear
php artisan test --filter=LibraryCategoryTest
```

The migration has already been applied in this workspace. Running it again reports nothing to migrate. The targeted command changes only this step's schema. Tests use the project's isolated testing database, not school records.

If the application is not running already:

```powershell
php artisan serve
```

Open the URL printed by that command and log in with your existing Admin account. Do not generate a new migration for the same table or run migrate:fresh on school data.

## Manual test

1. Open **Library → Categories**, or `/library/categories`.
2. Click **Add Category**. Enter `Computer Science`, a description, and Active. Save.
3. Expect a success message and the category in the list. Number of Books is 0 at this step.
4. Try adding `Computer Science` again. Expect a validation error without a duplicate record.
5. Edit the category, change the description and choose Inactive. Save. Search the description, then filter by Inactive.
6. Delete this unused category. Expect it to disappear and a success message.
7. Log in as Teacher and try `/library/categories/create`. Expect HTTP 403. A guest redirects to login. Librarians can read categories but cannot manage them.

Category creation, update and deletion record the authenticated actor in activities. When Books is implemented, test that a category with a book cannot be deleted; the automated test already verifies that guard against a temporary books table.

## Next step

Stop here and check Categories before Authors. Authors, Publishers and Books have not been started. The Library root currently redirects to Categories; dashboard statistics arrive after inventory and borrowing exist.

# Students module

The existing teal/blue admin layout is retained. This module now includes the student directory, admissions/editing, profiles, documents, family links, promotion, bulk actions, Excel transfer and printable PDF ID cards.

The latest Admissions and Promotion workflow, three-item sidebar, conditional bulk toolbar and certificates are documented in [STUDENTS_WORKFLOWS.md](STUDENTS_WORKFLOWS.md). That guide supersedes the earlier class-wide roll-number rule and directory bulk-action description below.

## Installation and deployment

These packages have already been installed in this workspace and are recorded in `composer.json` and `composer.lock`:

| Package | Installed version | Purpose |
| --- | --- | --- |
| `maatwebsite/excel` | 3.1.70 | XLSX exports, Excel imports, sample workbook and error report |
| `barryvdh/laravel-dompdf` | 3.1.2 | Filtered directory PDFs and printable ID cards |
| `chillerlan/php-qrcode` | 5.0.5 | Generate the ID-card QR image locally |

To add the dependencies to another checkout:

```powershell
composer require "maatwebsite/excel:~3.1" "barryvdh/laravel-dompdf:~3.1" "chillerlan/php-qrcode:~5.0"
```

For a checkout containing the updated lock file, use `composer install` instead. Then run:

```powershell
php artisan migrate
php artisan storage:link
npm install
npm run build
php artisan optimize:clear
```

Run `php artisan storage:link` once on each deployment so uploaded public photos can be served. The existing Windows junction in this workspace already points to `storage/app/public`. Photos use the public disk; admission documents remain on the private local disk and are downloaded through student-scoped routes.

If starting without academic options:

```powershell
php artisan db:seed --class=AcademicSetupSeeder
```

This adds Nursery, KG, Class 1–10, sections A/B and session 2026–2027 without duplicating existing names. Set `APP_URL` to the real application address on deployment so QR codes point to the correct student profile. PHP GD, ZIP, DOM/XML, fileinfo and mbstring extensions are needed; they are enabled in this workspace.

## Behavior

- Create forms show a read-only admission field. The server assigns `ADM-{admission year}-{yearly sequence}`, e.g. `ADM-2026-0001`. Client-supplied admission numbers cannot override create-form generation. Imports may include an optional `admission_no` column to preserve existing identifiers; otherwise they generate a number.
- A counter row is incremented inside the admission transaction. Database locking, transaction retries and the existing unique admission index prevent duplicate numbers. Migration seeds counters from existing generated numbers. Allocation also skips identifiers reserved by imported, edited or archived records. Sequences restart for each admission year.
- Roll numbers are now unique within an academic session, class and section. Archived students reserve their admission and roll numbers in that assignment. The new workflow migration replaces the earlier class-wide index.
- Dates display and are entered as `dd-mm-yyyy`; requests normalize them to ISO dates for database storage. Excel also accepts actual Excel date cells and ISO dates.
- Guardian relationships are Father, Mother, Uncle, Brother and Other. Legacy relationship labels appear as Other when editing.
- Student deletion archives with `deleted_at`. It preserves documents, photos, histories, attendance and other related records. Single and bulk deletion use confirmation dialogs.
- Siblings match trimmed, case-insensitive father names plus the exact contact phone, or a shared optional Family / Guardian ID. Use text phone cells and consistent phone formatting for matching.
- The directory supports debounced search, automatic dropdown filters, name/admission/roll sorting, session filtering and 10/25/50 rows per page. Reset remains available. Exports apply the same filters and sorting to **all matching students**, across every page.
- Directory bulk selection is limited to the current page and 50 students. Its toolbar appears only when rows are selected and offers Change status and Print ID Cards. Promotion now has its own screen and recorded decision history.
- The promotion screen filters current session/class/section, lets the user select active students, and takes a next session/class/section and first roll number. It checks current assignment again before committing. Left and Passed out statuses are supported alongside legacy status labels.
- Profile tabs are Overview, Guardian, Documents, Family, Attendance, Fees and Results. The last three are placeholders, as requested. Promotion and history remain in Overview. Document download/delete routes check that the document belongs to the student. The header includes ID-card and certificate PDF downloads.
- ID-card PDFs contain photo or initials, name, class/section, admission number and a locally generated QR code linking to the profile. Open the downloaded PDF and print it.

## Excel import

Open Students → Import, download the sample workbook, replace the example row and upload `.xlsx` or `.xls` (up to 5 MB). The first worksheet is imported. The page lists class, section and session IDs for the workbook's ID columns.

Required headings: `first_name`, `father_name`, `date_of_birth`, `gender`, `phone`, `address`, `admission_date`, `roll_number`, `status`. The template also includes optional last name, academic assignment, guardian fields and family ID. An optional `admission_no` column is accepted for existing identifiers.

Keep phone numbers and roll numbers as **text**, especially when they have leading zeros. Rows are checked against the admission validation rules, academic option IDs and database uniqueness. Valid rows import, invalid rows skip. The report identifies the original Excel row and its validation errors; it can also be downloaded as XLSX. Blank rows are ignored. Correct skipped rows and upload only those rows again to avoid duplicate roll numbers. Spreadsheet exports bind values as strings so user-entered formulas remain text.

## Files

All paths below are relative to the repository root. New migrations extend the database; previously applied migrations are retained.

| File | Purpose |
| --- | --- |
| `database/migrations/2026_10_01_000000_add_soft_deletes_to_students.php` | Adds the archival `deleted_at` column. |
| `database/migrations/2026_10_01_000100_format_numeric_admission_numbers.php` | Converts legacy numeric admission identifiers to the professional ADM format, preserving existing collisions. |
| `database/migrations/2026_10_01_000200_complete_students_module.php` | Adds yearly admission counters, family IDs and the class/roll unique index; allows the additional status labels. |
| `database/seeders/AcademicSetupSeeder.php` | Idempotently seeds the agreed classes, sections and session. |
| `database/seeders/DatabaseSeeder.php` | Includes academic setup in the standard seed flow. |
| `app/Models/Student.php` | Uses SoftDeletes, defines statuses/relationships, allows family IDs and queries siblings; retains document, attendance and history relationships. |
| `app/Models/StudentDocument.php` | Existing model for privately stored admission documents, retained. |
| `app/Models/StudentHistory.php` | Existing model for admission and change history, retained. |
| `app/Models/StudentAttendance.php` | Existing attendance model and relationship, retained. |
| `app/Http/Requests/StoreStudentRequest.php` | Validates admissions, relationship options, family IDs and class-wise roll uniqueness; normalizes date input. |
| `app/Http/Requests/UpdateStudentRequest.php` | Reuses admission validation while excluding the current student from admission uniqueness; inherited roll validation also excludes the current student. |
| `app/Services/AdmissionNumbers.php` | Allocates duplicate-safe admission numbers through the locked yearly counter. |
| `app/Services/StudentRecords.php` | Saves student details, photo/documents and histories atomically, retries database conflicts, and cleans up failed uploads. |
| `app/Services/StudentDirectory.php` | Shares filter validation and sorted queries between directory and exports. |
| `app/Services/StudentPromotion.php` | Validates eligibility/destination rolls, changes assignment and records old/new values in history. |
| `app/Http/Controllers/StudentController.php` | Directory pagination, create/edit/show, generated admissions and soft deletion; passes siblings to the profile. |
| `app/Http/Controllers/StudentRecordsController.php` | Academic options, scoped document upload/download/deletion and individual promotion. |
| `app/Http/Controllers/StudentBulkController.php` | Promotion screen and transactional bulk status/delete/promote actions. |
| `app/Http/Controllers/StudentTransferController.php` | Filtered Excel/PDF downloads, import page, sample template, workbook validation and downloadable error reports. |
| `app/Http/Controllers/StudentIdCardController.php` | Embeds a public-disk photo and local QR image in the ID-card PDF. |
| `app/Exports/StudentExport.php` | Queries all filtered students and maps human-readable Excel columns and dates. |
| `app/Exports/StudentImportTemplate.php` | Builds a text-safe sample workbook with matching heading names and available academic IDs. |
| `app/Exports/ImportErrorExport.php` | Downloads Excel row numbers and validation messages. |
| `app/Imports/StudentImport.php` | Normalizes spreadsheet rows, validates them, saves valid students and collects errors. |
| `app/Imports/StudentWorkbookImport.php` | Restricts import to the first worksheet, so report row numbers are unambiguous. |
| `routes/web.php` | Registers export/import/template/report/bulk/promotion/ID-card routes before resource binding; retains document and individual promotion routes. |
| `resources/views/layouts/admin.blade.php` | Retains UTF-8 meta charset and uses a correctly encoded title separator. |
| `resources/views/students/index.blade.php` | Responsive, sortable directory with selection, exports, pagination, empty states and student confirmation modal. |
| `resources/views/students/create.blade.php` | Existing admission page using the updated shared form. |
| `resources/views/students/edit.blade.php` | Existing edit page with the corrected separator and updated shared form. |
| `resources/views/students/show.blade.php` | Accessible profile tabs, guardian/siblings section, placeholders and ID-card download. |
| `resources/views/students/promotion.blade.php` | Current-assignment filters, selectable active students and destination assignment form. |
| `resources/views/students/import.blade.php` | Template download, ID reference, workbook upload and row-wise report. |
| `resources/views/students/partials/form.blade.php` | Read-only generated admission field, dd-mm-yyyy dates, relationship dropdown, family ID and initials photo preview. |
| `resources/views/students/partials/avatar.blade.php` | Shared initials-circle fallback when a photo is missing from public storage. |
| `resources/views/students/partials/filters.blade.php` | Directory search, status/gender/class/section/session filters, page size and Reset. |
| `resources/views/students/partials/bulk.blade.php` | Bulk action/status selector and bulk delete confirmation modal. |
| `resources/views/students/partials/destination.blade.php` | Shared next session/class/section and starting-roll fields. |
| `resources/views/students/partials/documents.blade.php` | Document list, download, deletion and upload form inside the Documents tab. |
| `resources/views/students/partials/records.blade.php` | Individual promotion and date-formatted student history inside Overview. |
| `resources/views/students/pdf/directory.blade.php` | Landscape PDF table for the filtered directory. |
| `resources/views/students/pdf/id-card.blade.php` | Print-friendly ID-card layout with embedded photo/initials and QR code. |
| `resources/js/app.js` | Live filtering/search with stale-request cancellation, sorting, current-filter export links, selection, confirmation dialogs, preview initials and keyboard-accessible tabs. |
| `resources/css/app.css` | Extends the existing UI styles for circle avatars, tabs, bulk tools and responsive layouts. |
| `composer.json`, `composer.lock` | Declare and lock the Excel, PDF and QR dependencies. |
| `public/build/manifest.json`, `public/build/assets/*` | Compiled browser assets generated by Vite. |
| `tests/Feature/StudentTest.php` | Existing CRUD/document/promotion tests updated for archival, generated admissions and unique rolls. |
| `tests/Feature/StudentModuleTest.php` | Tests yearly numbering, date/roll validation, siblings, sorting/pagination, bulk rollback, Excel import/report/export and PDF generation. |
| `tests/browser/students.mjs` | Checks real-browser filters, search, sorting, export links, checkboxes, confirmation dialogs, photo previews, tabs and mobile overflow. |
| `tests/integration/admission-concurrency.mjs` | Runs eight simultaneous admissions against an isolated temporary SQLite database and asserts unique consecutive numbers. |
| `tests/support/admission-worker.php` | PHP worker for the isolated concurrency check. |

## Validation

```powershell
php artisan test
node tests/integration/admission-concurrency.mjs
npm run build
php artisan view:cache
```

The latest feature suite passes 24 tests with 312 assertions. The earlier concurrency check passes for eight simultaneous admissions. The real-browser check passes at desktop and 390px/320px widths. The build and Blade compilation pass.

For the browser check, run the Laravel/Vite servers and an isolated Chrome session with debugging port 9222, then `node tests/browser/students.mjs`. It opens/cancels deletion dialogs without deleting students and expects at least one existing student. A screenshot is saved at `storage/app/students-module-desktop.png`.

The concurrency check uses SQLite. MySQL-specific concurrent execution has not been run here. Existing fee/attendance/results modules are not implemented by these changes; their requested profile tabs are placeholders.

Package API references: [Laravel Excel import documentation](https://docs.laravel-excel.com/3.1/imports/concerns.html), [Laravel Excel installation](https://docs.laravel-excel.com/3.1/getting-started/installation.html), [Laravel DomPDF](https://github.com/barryvdh/laravel-dompdf), [PHP QR Code](https://github.com/chillerlan/php-qrcode/tree/v5.0.5).

# Student List redesign

Open **Students → All Students** (`/students`). The student directory now uses an EduDash-style single white card, rounded corners, comfortable rows and teal `#0A7EA4` controls. Its header says Student List with Dashboard / Student List breadcrumbs and Add Student linking to Admissions → New Admission. The existing sidebar, topbar, admin/teacher dashboards and other forms were not redesigned. All new directory styles are scoped to `.student-list-page`.

## Behavior

- Toolbar: Excel/PDF exports preserve all active filters; Print prints the currently displayed page; Import remains accessible in the Export menu. Search includes full name, admission number, phone, roll number and the existing father-name match. It updates after 400ms, aborts older requests and refreshes table/counts/chips/pagination without a page reload. A loading skeleton and accessible status message appear during fetching.
- Filter dropdown: Class, Section, Status, Gender and Session. Filters apply on selection or form submission. Changing Class clears an old Section selection. Removable chips show active filters; removing Status allows all statuses. Reset returns to the default Active-only list.
- Pagination: 10/25/50/100 rows, server-side only, filters preserved. Serial numbers continue across pages; footer shows the visible range and total with teal Prev/page numbers/Next controls.
- Table: selection, serial, teal admission-profile link, 40px photo/colored initials beside name and roll number, class/section, father, phone, gender, status and kebab actions. Class and section are eager loaded. Name/admission/class/status sort on the server through validated keys. Unassigned class/section badges are shown explicitly.
- Status: Active green, Left/Left School red, Passed out/Graduated blue, other statuses neutral. Existing status values and soft-deleted records are preserved.
- Kebab: View, Edit, Print ID Card and Delete. Delete requires confirmation and uses existing CSRF-protected soft deletion. Row menus position inside the viewport rather than being clipped by the scrolling table.
- Selection is limited to the displayed page. The selected bar supports status changes, ID-card PDF download, promotion and Clear. Promotion opens a destination dialog and reuses the existing transactional promotion service. Bulk limits were raised from 50 to 100 to match the largest page size; invalid promotion/status/roll conflicts still reject atomically.
- Class pills use actual Academic Setup classes, including early-year classes when configured. The implementation does not invent or seed missing classes. One cached grouped student query supplies class and section counts; the counts follow search/status/gender/session and exclude the selected class/section so other choices remain visible. The cache lasts five minutes and uses the existing student-change data revision for invalidation. Section pills appear when a class is selected.
- On mobile the toolbar wraps, pills scroll horizontally and the table scrolls within its card. Headers stay visible while scrolling the table; the page itself does not overflow horizontally. No new JavaScript framework or dependency was installed.

## Files created

| File | Purpose |
| --- | --- |
| `resources/css/students-list.css` | Isolated teal directory, toolbar, table, pills, responsive, skeleton and print styling. |
| `resources/views/students/partials/student-row.blade.php` | Eager-loaded student row with profile/ID-card links and action menu. |
| `resources/views/students/partials/status-badge.blade.php` | Consistent status pills. |
| `resources/views/students/partials/filter-dropdown.blade.php` | Accessible filter panel with the five filters and Reset. |
| `resources/views/students/partials/filter-chips.blade.php` | Removable active filters and reset link. |
| `resources/views/students/partials/class-pills.blade.php` | Cached class/section count navigation. |
| `resources/views/students/partials/pagination.blade.php` | Compact server-side teal pagination. |
| `tests/Feature/StudentDirectoryPageTest.php` | Active defaults, eager loading, counts/cache invalidation, sorting, pagination serials and soft delete. |
| `storage/app/verify-student-list.mjs` | Local browser checks for responsive layout, live filtering, selection, menus and dialogs. |
| `docs/student-list-redesign.md` | File guide, behavior and manual checks. |

## Files changed

| File | Purpose |
| --- | --- |
| `resources/views/students/index.blade.php` | Student List header, one directory card, component table and confirmation dialogs. |
| `resources/views/students/partials/filters.blade.php` | Export/search/filter/rows toolbar. |
| `resources/views/students/partials/bulk.blade.php` | Slim selected-student action bar. |
| `resources/views/students/partials/avatar.blade.php` | Optional directory-specific colored avatar classes; other avatar uses retain their styling. |
| `app/Models/Student.php` | Class/section relationships for eager loading. |
| `app/Services/StudentDirectory.php` | Validated class/status sorting, 100-row option, eager loading and cached grouped counts. |
| `app/Http/Controllers/StudentController.php` | Passes directory count data to the list. |
| `app/Http/Requests/StudentBulkActionRequest.php` | Accepts up to 100 selected students for status/ID cards. |
| `app/Http/Controllers/StudentBulkController.php` | Accepts up to 100 selected students for the existing atomic promotion action. |
| `resources/js/app.js` | Directory live search/skeleton/refresh, selection controls, printing and positioned menus; other workflows remain intact. |
| `resources/css/app.css` | Imports the scoped student directory stylesheet. |
| `tests/Feature/StudentTest.php` | Updates the empty-state expectation to the requested No students found label. |
| `public/build/manifest.json`, `public/build/assets/*` | Rebuilt UI assets. |

## Commands

```powershell
npm run build
php artisan test
```

No new migration or seeder is required. The build was run locally.

## Manual testing

1. Sign in as Admin and open `/students`: default status must be Active, existing sidebar/header unchanged, and the button must open Admissions → New Admission.
2. Search by full name, admission number, phone or roll number. Watch the loading state; the table, total, chips and pills should update after roughly 400ms. Quickly type two different searches: only the newest response should remain. Empty searches show No students found with clear/add buttons.
3. Open Filter, choose Class, Section, Gender and Session. Verify chips reflect selections and removing them clears the corresponding filter. Remove the Active chip to include Left/Passed out records. Reset returns to Active only. Click a class pill: section sub-pills appear with counts. Create/update/delete a student: counts refresh on the next request.
4. Sort Name, Admission No, Class and Status both directions. Choose 25/50/100 rows. Go to page 2 and verify serials continue from the previous page and the range/total is correct.
5. Confirm uploaded photos display; missing photos show colored initials. Click an admission number or View to open the profile. Check unassigned warnings and status colors.
6. Open a kebab menu near the bottom of the table. It must stay visible. Edit and Print ID Card should work. Delete opens a confirmation dialog; Cancel preserves the record, Confirm archives it.
7. Select individual rows or Select all. Verify N selected, Clear, change status, ID cards and promotion. Promotion asks for destination session/class/section and roll start; conflicts reject the entire batch. Selection clears when the table is reloaded through filters/search and never selects hidden pages.
8. Excel/PDF should export the filtered set. Print should print the current visible page without the sidebar or toolbar. Resize to 390px/320px: toolbar/pills/table remain usable without page-wide horizontal overflow. Scroll the table: its header stays visible.

Focused tests: `php artisan test --filter=StudentDirectoryPageTest`. Existing student/admission/bulk/export tests are included in the full suite.

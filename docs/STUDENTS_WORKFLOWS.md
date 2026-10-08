# Students navigation, Admissions and Promotion

The existing sidebar icons, teal active highlight, form fields, panels, table and tab styles are reused. Primary buttons use a teal/blue gradient, approval/ID-card buttons use a green/teal gradient, certificates and promotion confirmation use a violet/blue gradient, and destructive buttons use a rose gradient. Import and Export remain outlined.

## Commands

No new packages are required. The existing Laravel Excel, DomPDF and QR-code packages are reused. The new migration has been applied in this workspace and the browser assets have been built.

For another checkout:

```powershell
composer install
php artisan migrate
php artisan storage:link
npm install
npm run build
php artisan optimize:clear
```

If academic options have not been created:

```powershell
php artisan db:seed --class=AcademicSetupSeeder
```

Run `php artisan test` to validate the workflows. `node tests/browser/students.mjs` runs the interactive browser checks when the application is served at `http://127.0.0.1:8000` and an isolated Chrome debug session is available on port 9222. The test opens and cancels confirmation dialogs; it does not approve, reject, promote or delete workspace records.

## Navigation and routes

Students has exactly three sidebar links:

| Link | URL | Route name |
| --- | --- | --- |
| All Students | `/students` | `students.index` |
| Admissions | `/admissions` | `admissions.index` |
| Promotion | `/promotion` | `promotion.index` |

The parent expands automatically on student, admission or promotion pages. The current child receives the teal highlight. Plain JavaScript updates `aria-expanded`, keyboard focusability and the chevron; CSS grid animates opening and closing and respects reduced-motion preferences.

All Students defaults to Active. Selecting All statuses explicitly removes the status constraint; Left and Passed-out records can also be selected separately. Header Import links to the existing import page, Export has Excel/PDF choices that retain current filters, and Add Student links to `/admissions/new`. The bulk toolbar appears only while rows are checked and offers Change status or a combined Print ID Cards PDF.

Profile tabs are Overview, Guardian, Documents, Family, Attendance, Fees and Results. Siblings are in Family. The last three remain placeholders. Leaving, Character and Bonafide certificate choices download printable PDFs with student details and space for school authorization. Downloading a certificate does not change the student's status.

### Admissions routes

| Method | URL | Purpose |
| --- | --- | --- |
| GET | `/admissions` | Applications tab with search, status and class filters |
| POST | `/admissions/applications` | Save a pending application |
| GET | `/admissions/new` | Existing student form within New Admission tab |
| POST | `/admissions/new` | Admit a student directly |
| GET | `/admissions/applications/{application}/review` | Complete student details for approval |
| POST | `/admissions/applications/{application}/approve` | Approve, create one student and link it atomically |
| POST | `/admissions/applications/{application}/reject` | Reject a pending application |
| GET | `/admissions/register` | Date-wise register, including archived students |
| GET | `/admissions/register/export/{xlsx|pdf}` | Export every matching register row |

Applications store applicant name, father name, phone, applying class, previous school and notes. Review status starts pending. Approval requires the normal student details, an Active status and a complete academic assignment. The shared admission form is prefilled from the application. Approval generates `ADM-YYYY-0001`, locks the application, saves the student and marks the application approved with `student_id` in one transaction. Repeated approval or rejection is refused. Missing or invalid student details keep the application pending.

The register filters admission date inclusively by From/To date and optionally by current class. It shows archived students for a complete admission count. Dates use dd-mm-yyyy; database dates remain ISO formatted. XLSX/PDF exports use the same date/class query as the register and include all pages.

### Promotion routes

| Method | URL | Purpose |
| --- | --- | --- |
| GET | `/promotion` | Promote tab: filter active students and choose decisions |
| POST | `/promotion` | Confirm and save all selected decisions atomically |
| GET | `/promotion/history` | Filterable, paginated promotion history |

Select current session/class/section, choose rows and a decision for each selected student, then confirm:

| Action | Result |
| --- | --- |
| Promote | Changes to a different class in the selected next session and target section; generates a roll number. |
| Detain | Keeps current class/section, moves to the next session and generates a roll number there. |
| Left | Sets status Left and preserves the previous academic assignment. |
| Passed out | Sets status Passed out and preserves the previous academic assignment. |

Class 10 or Grade 10 defaults to Passed out in its row selector. A checked row is required for processing. Next session/class/section become required only when applicable; terminal decisions need no destination. Each page supports up to 50 students.

Roll numbers are unique per **academic session + class + section**, replacing the earlier class-wide rule. The same roll number may exist in another section or session. The allocator locks a counter for the destination tuple, includes existing/archived numeric rolls when finding its next number, and increments it inside the transaction. Sequential numbering starts at 1 in an empty destination; existing numeric rolls are skipped. Allocation and processing occur in ascending student-ID order.

The workflow rechecks each student's current status and source assignment after locking. If one row is invalid, all student changes, counter updates and promotion histories roll back. Promote and Detain require a different session; Promote also requires a different class. The history records source session/class/section, destination fields, action, actor and date. Destination fields are null for Left and Passed out. It retains links to archived students.

`reviewed_by` and `promoted_by` come from the authenticated request user, never from submitted input. This project currently has no login workflow; these values remain null when there is no signed-in user. The UI displays that fact. They will populate when the existing application is connected to authentication; no fictitious staff ID is assigned.

The legacy student create/store, bulk and individual-promotion routes remain compatible with earlier callers. The new sidebar and controls use the canonical URLs above. Individual promotions also write to the new promotion history table. Existing pre-migration promotions remain in student history; their unknown actor/session details are not fabricated into the new table.

## Files explained

Paths below are relative to the project root.

| File | Explanation |
| --- | --- |
| `database/migrations/2026_10_01_000300_add_admissions_and_promotion_workflows.php` | Creates application, promotion and roll-counter tables; replaces the roll unique index with session/class/section uniqueness. Rollback refuses to reinstate the old index when newly valid duplicate class-wide rolls exist. |
| `app/Models/AdmissionApplication.php` | Application fields, review timestamp and student/reviewer relationships; linked archived students remain accessible through the relationship. |
| `app/Models/Promotion.php` | Decision labels, date cast and archived-student/actor relationships for promotion history. |
| `app/Models/Student.php` | Adds the `promotions()` relationship to the existing student model. |
| `app/Http/Requests/StoreAdmissionApplicationRequest.php` | Validates applicant, father, phone, target class and optional previous-school/notes fields. |
| `app/Http/Requests/ApproveAdmissionRequest.php` | Reuses student validation and requires Active status, class, section and session for approval. |
| `app/Http/Requests/AdmissionRegisterRequest.php` | Normalizes dd-mm-yyyy From/To dates and validates the inclusive range and optional class. |
| `app/Http/Requests/RunPromotionRequest.php` | Validates selected students, exactly one allowed decision per row, source filters and conditional destination requirements. |
| `app/Http/Requests/StudentBulkActionRequest.php` | Restricts the directory toolbar to status changes and ID-card printing for up to 50 valid students. |
| `app/Http/Requests/StoreStudentRequest.php` | Updates roll validation to match session/class/section uniqueness; inherited update validation excludes the current student. The 10 MB photo limit is retained. |
| `app/Http/Controllers/AdmissionsController.php` | Applications list/create/review/approve/reject, direct admission form, register and matching Excel/PDF exports. |
| `app/Http/Controllers/PromotionController.php` | Promote and History tabs, current-assignment query, decision execution and history filters. |
| `app/Http/Controllers/StudentActionsController.php` | Transactional selected-row status changes, combined ID-card downloads and three certificate PDFs. |
| `app/Http/Controllers/StudentIdCardController.php` | Delegates existing single-card downloads to the shared card renderer. |
| `app/Services/AdmissionRegister.php` | Shared date/class query for register display and exports, including archived records. |
| `app/Services/PromotionWorkflow.php` | Runs selected Promote/Detain/Left/Passed-out decisions in a single retriable database transaction. |
| `app/Services/RollNumbers.php` | Locked destination counter and next numeric roll allocation within the promotion transaction. |
| `app/Services/StudentPromotion.php` | Uses destination-scoped roll checks and writes both old/new student history and structured promotion history. |
| `app/Services/StudentIdCards.php` | Shared photo/initials/QR rendering for single and multiple ID-card PDF downloads; embeds small photo thumbnails so large uploads do not inflate bulk PDFs. Original photos are retained. |
| `app/Services/StudentDirectory.php` | Defaults status to Active only when the request omits status, allowing explicit All statuses. |
| `app/Exports/AdmissionRegisterExport.php` | Reuses existing Excel columns/value binding with the date-filtered register query. |
| `routes/web.php` | Registers canonical Admissions/Promotion endpoints, directory bulk actions and certificates while retaining prior endpoints. |
| `resources/views/components/sidebar.blade.php` | Exactly three child links, route-aware expanded state, icons and active child highlight. |
| `resources/views/students/index.blade.php` | Header Import/Export/Add controls, canonical admission link and conditional bulk toolbar include. |
| `resources/views/students/create.blade.php` | Reuses the admission form within Admissions tabs and posts to the canonical direct-admission route. |
| `resources/views/students/show.blade.php` | Seven profile tabs, separate Family content, Print ID Card and Certificates dropdown. |
| `resources/views/students/import.blade.php` | Updates workbook guidance to the new session/class/section roll uniqueness rule. |
| `resources/views/students/partials/form.blade.php` | Reused student form; academic fields become required and status is Active-only when approving an application. |
| `resources/views/students/partials/filters.blade.php` | Shows the active status choice and labels Passed out as Passed-out. |
| `resources/views/students/partials/bulk.blade.php` | Hidden-by-default status/ID-card form that appears with selected rows. |
| `resources/views/admissions/partials/tabs.blade.php` | Applications, New Admission and Register navigation. |
| `resources/views/admissions/index.blade.php` | Application table, badges, filters, review/reject controls and new-application form. |
| `resources/views/admissions/review.blade.php` | Prefilled student form and confirmation before approving an application. |
| `resources/views/admissions/register.blade.php` | Date range/class filters, register table, pagination and export dropdown. |
| `resources/views/admissions/pdf/register.blade.php` | Print-ready admission register with dates and archived markers. |
| `resources/views/promotion/partials/tabs.blade.php` | Promote and History navigation. |
| `resources/views/promotion/index.blade.php` | Current filters, checkboxes, per-row decision selector, Class 10 default and destination form. |
| `resources/views/promotion/history.blade.php` | Source/destination/action/actor/date history table with filters and pagination. |
| `resources/views/students/pdf/id-cards.blade.php` | Shared single/multiple card PDF layout, one card per page. |
| `resources/views/students/pdf/certificate.blade.php` | Leaving, Character and Bonafide PDF templates with student data and authorization space. |
| `resources/js/app.js` | Sidebar animation state, dropdown dismissal, conditional selection bar, promotion row/target validation and shared confirmation dialog; retains live search, previews and profile tabs. |
| `resources/css/app.css` | Submenu animation, teal child styles, workflow tabs, dropdown menus, gradient buttons and application badges. |
| `tests/Feature/AdmissionsPromotionTest.php` | Tests navigation, application approval/rejection, repeated-review prevention, register exports, certificates, default statuses, selected-row printing, mixed decisions and rollback. |
| `tests/Feature/StudentModuleTest.php` | Updates the prior conflict fixture to the new destination session/class/section rule. |
| `tests/browser/students.mjs` | Real-browser navigation, toolbar, status filters, family/profile tabs, workflow confirmations and mobile checks. |
| `public/build/manifest.json`, `public/build/assets/*` | Compiled production assets produced by Vite. |
| `docs/STUDENTS_MODULE.md` | Earlier Students module guide, linked to this updated workflow guide. |

## Verification

The full Laravel suite passes **24 tests / 312 assertions**. Vite build and Blade compilation pass. Browser checks pass for exactly three sidebar links, expansion and active routing, default/all status filters, toolbar visibility, live search, sorting, seven profile tabs, application/register/promotion screens, confirmation dialogs and desktop/mobile layouts at 390px and 320px. The directory screenshot is stored in `storage/app/students-module-desktop.png`.

Tests cover SQLite, the configured database here. MySQL-specific concurrent execution has not been run. No workspace student or application data was modified by browser checks.

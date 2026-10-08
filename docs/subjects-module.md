# Subjects module

Open **Subjects** in the sidebar. The page has two linked tabs: **All Subjects** and **Class-wise Subjects**. Forms, tables, badges, buttons, confirmation dialogs, filtering behavior and navigation reuse the existing admin UI.

## Commands

Run from `C:\school management system`:

```powershell
php artisan migrate
php artisan db:seed --class=SubjectSeeder
npm run build
php artisan test
php artisan serve
```

For only this module's tests:

```powershell
php artisan test --filter=SubjectsModuleTest
```

No additional dependencies are required. Use the targeted seeder above on an existing installation; the root `DatabaseSeeder` also calls it during full-project setup. The root seeder retains this project's existing demo-user behavior.

## How it works

**All Subjects:** Search names/codes, filter type or status, sort columns (including class counts), and paginate. Filters apply automatically; Apply also works without JavaScript. Add/Edit validates a unique name and unique code. Codes normalize to uppercase and permit letters, digits, hyphens and underscores. Types are theory/practical/both; categories are compulsory/optional/elective; statuses are Active/Inactive. The existing unique name constraint is retained. Archived subject names/codes remain reserved.

Delete archives through soft deletes. It is blocked if there is any class pivot, teacher assignment, or recorded mark. A visible error explains why. Teacher assignments count as references even when their teacher is archived. Inactivation is available through Edit when a subject should stop appearing in new selections while its history is retained.

**Class-wise Subjects:** Select a class, tick subjects, enter total/pass/practical marks, compulsory flag and display order, then Save. Only active, unarchived subjects appear. Only checked rows are validated/saved; unchecked active subjects are removed. Total must be at least 1; pass and practical marks must each be between 0 and total. Practical marks are included within total rather than added on top. Display order is a nonnegative integer. Per-class compulsory flags may differ from the subject's default category. A unique composite key prevents duplicate assignments.

Saving uses a transaction and locks the class and selected subjects. Existing inactive subject links are preserved because users cannot see or edit those links here. Removing a subject with marks in that class is blocked and rolls back all changes. Selecting no subjects clears active links when no protected marks exist.

**Copy:** Select another class and confirm Copy Subjects. This replaces the target's active selection with the source's active subjects, including marks, compulsory flags and display order. It preserves target inactive links. Copying from the same class or a class with no active subjects is rejected. If replacement would remove a subject with recorded marks, nothing changes.

There was no exams/marks table in this project. `subject_marks` and `SubjectMark` provide the relational foundation and deletion guard; entering exam marks is outside this module's UI. Future marks writers should transact and lock their Subject and SchoolClass parent rows to coordinate with assignment/deletion checks.

## Existing data and teacher compatibility

The migration extends the subjects table created by the Teachers module. Existing subject IDs/names are preserved, assigned deterministic codes such as `SUB-0001`, and defaulted to Active/theory/compulsory. Distinct class links from existing teacher assignments are backfilled into `class_subject` with default marks settings. Existing teacher assignments are preserved.

The teacher setup panel may still add a subject by name; `Subject` generates a unique code for that legacy workflow. Customize its code, category and type in Subjects → Edit. Inactive/archived subjects cannot be reused through teacher quick setup, and new teacher assignments only accept active, unarchived subjects. Existing assignments remain visible for reference.

## Seeder defaults

The seeder adds English, Urdu, Mathematics, Science, Physics, Chemistry, Biology, Computer, Islamiat, Pak Studies, Social Studies and Drawing, plus Class 1–10 if missing. These are editable starting templates:

| Classes | Default subjects |
| --- | --- |
| 1–5 | English, Urdu, Mathematics, Science, Islamiat, Social Studies, Drawing |
| 6–8 | English, Urdu, Mathematics, Science, Computer, Islamiat, Pak Studies, Social Studies |
| 9–10 | English, Urdu, Mathematics, Physics, Chemistry, Biology, Computer, Islamiat, Pak Studies |

Totals default to 100 and pass marks to 33. Physics/Chemistry/Biology/Computer default to both with 25 practical marks. Drawing defaults to practical with 100 practical marks. Computer and Drawing default to optional; other defaults are compulsory. Higher classes can adjust Biology/Computer selections to match their curriculum.

Running the seeder again does not duplicate rows, overwrite existing subject details or marks settings, reactivate subjects, or restore archived subjects. It adds any missing default links. If you intentionally remove a default link, rerunning the seeder adds it again; run it for initial setup rather than as a periodic task. A clean install has 12 subjects, 10 numbered classes and 77 class links.

## File guide

Paths are relative to the project root.

| File | Purpose |
| --- | --- |
| `database/migrations/2026_10_01_000600_complete_subjects_module.php` | Extends subjects, backfills codes and existing teacher class links, creates the unique `class_subject` pivot and `subject_marks` foundation. |
| `app/Models/Subject.php` | Soft deletes, assignable attributes, generated legacy codes, class/marks/teacher relationships. |
| `app/Models/SchoolClass.php` | Adds the subject relationship with all pivot fields and display-order sorting. |
| `app/Models/SubjectMark.php` | Student/class/subject mark relationships and decimal casting for future exams. |
| `app/Http/Requests/SubjectRequest.php` | Add/Edit validation, normalized codes, unique name/code and allowed type/category/status values. |
| `app/Http/Requests/ClassSubjectsRequest.php` | Selects checked rows, rejects duplicate/inactive subjects, validates class IDs and marks, and validates copy source/target. |
| `app/Services/ClassSubjects.php` | Transactional class selection replacement and copying, locks, preservation of inactive links and marks protection. |
| `app/Http/Controllers/SubjectController.php` | Search/filter/sort/pagination with aggregate class counts, CRUD and protected soft deletion with flash messages. |
| `app/Http/Controllers/ClassSubjectController.php` | Loads class/subject data eagerly, renders the class editor, and delegates save/copy to the service. |
| `database/seeders/SubjectSeeder.php` | Common subject and Class 1–10 starter assignments without overwriting existing settings. |
| `database/seeders/DatabaseSeeder.php` | Registers SubjectSeeder for full installation seeding. |
| `routes/web.php` | Resource and class save/copy routes; static class routes precede subject bindings. |
| `resources/views/components/sidebar.blade.php` | Adds the Subjects link with an active state, preserving icon-collapse behavior. |
| `resources/views/subjects/index.blade.php` | Directory, auto filters, sortable headers, class counts, pagination, Edit/Delete/Add actions. |
| `resources/views/subjects/form.blade.php` | Shared create/edit form using the existing section and field styles. |
| `resources/views/subjects/classes.blade.php` | Class selector, tickable subject/marks table and copy controls. |
| `resources/views/subjects/partials/tabs.blade.php` | Both tabs with active/current-page states. |
| `resources/views/subjects/partials/feedback.blade.php` | Reuses validation/success feedback and adds visible blocked-deletion errors. |
| `resources/js/app.js` | Enables mark inputs for checked subject rows; reuses existing auto filters and action confirmation dialogs. |
| `resources/css/app.css` | Small additions for filter and marks input styling that match existing panels/forms. |
| `app/Http/Requests/AssignmentRequest.php` | Restricts new teacher assignments to active, unarchived subjects. |
| `app/Http/Controllers/TeacherAssignmentController.php` | Filters teacher subject choices and locks/rechecks subject status before assigning. |
| `app/Http/Controllers/TeacherController.php` | Gives a validation message when quick setup encounters an inactive/archived subject name. |
| `tests/Feature/SubjectsModuleTest.php` | Tests CRUD, unique codes, filters/sorting/pagination, class marks validation, copying, inactive links, marks deletion guards, rollback and repeatable seeding. |
| `docs/subjects-module.md` | This command, behavior, seed-default and source-file guide. |

Vite regenerates `public/build/manifest.json` and hashed CSS/JS assets during `npm run build`; modify source files rather than generated assets.

# Teachers module

The module uses the existing admin layout, student table/form classes, sidebar animation, profile tabs, feedback component and confirmation dialog. Open **Teachers → All Teachers, Assignments, Leaves or Payroll**.

## Commands

From `C:\school management system`:

```powershell
# Existing project dependencies already include Laravel Excel and dompdf.
composer install
npm install

# Add the Teachers tables without replacing existing school records.
php artisan migrate

# Required for public teacher photos. Skip if public/storage already exists.
php artisan storage:link

npm run build
php artisan test
php artisan serve
```

For frontend development, run `npm run dev` in a second terminal instead of rebuilding after every change. To run only the new coverage:

```powershell
php artisan test --filter=TeachersModuleTest
```

The migration and production frontend build were run during implementation. The project's photo storage link already existed. No real teachers or payroll entries were seeded.

## First use

1. On Add/Edit Teacher, use **Add new department** or **Add new designation** directly below the Employment dropdown. Enter a name and click **Add Department** / **Add Designation**; the new option is saved and selected without reloading or losing form entries. These controls start expanded when there are no options. **Manage teacher setup** also remains available for departments, designations, subjects and additional leave types. Casual (12 paid days), Sick (10 paid days), and Unpaid (365 days) are installed by the migration.
2. Add teachers. Employee IDs use the current save year, e.g. `EMP-2026-001`. A transactional counter reserves numbers, including those belonging to archived teachers. CNIC must match `12345-1234567-1` and remains unique even after archiving.
3. To create a Teacher user, select the login checkbox and supply a unique login email, password and confirmation. Passwords are hashed by the existing User model. For an existing account, an empty password preserves the password. Account creation is implemented; this app currently has no login routes or authorization middleware. This module follows the existing workspace access model. Creating a role does not itself implement a login page or restrict routes.
4. Create classes, sections and academic sessions in existing **Academic Setup**. Add subjects in teacher setup, then assign teachers. The same class/section/subject/session cannot be assigned twice. A class/section has one class teacher per session, and a teacher may lead one class/section per session. Set or clear that role on existing assignment rows. Workload shows assignment counts and weekly periods across all sessions; timetable slots are not modeled.
5. Apply leaves. The list and calendar use the selected month; choosing a teacher also shows that calendar year's balance. Pending and approved requests reserve days. Rejected requests release them. Overlapping pending/approved requests are prevented. Dates are inclusive, weekends count, and a request must stay within one calendar year. Submit separate requests for a leave crossing New Year. Reviews require remarks and can only change Pending requests.
6. On Payroll, select a month, record attendance/advances and review pending leaves, then generate. All eligible teachers are processed in one database transaction. Existing payrolls are skipped. Download slips, mark records paid, or download the monthly summary PDF.

## Payroll calculation

The Salary form now stores House, Medical, Transport and Other Allowance separately, plus Fixed Deductions, Bank Name and Account No. Only Basic Salary is required (minimum Rs. 1); optional amounts default to zero. Every amount has an Rs. prefix. Gross and Net are read-only live totals; server validation independently prevents deductions exceeding gross. New amounts are `decimal(10,2)` and model casts return two decimal places. The expansion migration copies old allowances into Other Allowance and old deductions into Fixed Deductions. Existing payroll snapshots are preserved.

Additional files: `database/migrations/2026_10_01_000500_expand_teacher_salary_structures.php` adds/backfills the columns and preserves aggregate values on rollback; `resources/views/teachers/partials/salary.blade.php` renders the shared create/edit Salary section. `SalaryStructure` calculates gross/net totals; `TeacherRecords` saves components and keeps legacy totals synchronized; `TeacherPayroll` uses component totals; `TeacherRequest` validates gross-based deductions; the profile displays all components and bank details. Imports support both new component headers and legacy allowance/deduction headers.

- Eligible teachers are not archived, are Active or On Leave, and joined on or before month end. Inactive teachers are excluded.
- Basic salary and allowances are prorated for a joining month using eligible calendar days divided by that month's days. Fixed deductions are taken once in full.
- Deductible days are the unique dates in the month covered by recorded Absent/Leave attendance or approved unpaid leave, on or after joining. Approved paid leave removes those dates. The same date is never deducted twice.
- Absence deduction = configured full monthly basic salary / calendar days in month × deductible days, rounded to the nearest paisa. Weekends count for approved unpaid leave; no school holiday/workweek configuration exists here.
- Unrecorded attendance days cause no deduction. Review attendance completeness before generation. A Leave attendance entry without approved paid leave counts as an unpaid day.
- Net salary = prorated basic + prorated allowances − fixed deductions − absence deduction − advances assigned to that month.
- Negative net salary, a missing salary structure, or pending leave in the month rejects and rolls back the whole batch. Monetary calculations are rounded in integer paisa before saving decimal amounts.
- Generated records snapshot salary amounts. Salary changes affect subsequent generation, not existing slips. Attendance, leave application/review, and advances in generated months are locked; rerunning generation preserves existing entries, including paid entries. Payment marking is idempotent and records a timestamp.

## Import and export

Download the CSV template from Import Teachers. CSV and XLSX are supported, up to 10 MB. Headers are `name, cnic, gender, phone, email, department, designation, joining_date, status, basic_salary, house_allowance, medical_allowance, transport_allowance, other_allowance, fixed_deductions, bank_name, account_no, qualification, experience`. Use existing department/designation **names**, a formatted CNIC, and joining dates stored as `YYYY-MM-DD` text. Keep phone/CNIC columns as text to retain leading zeros. Basic salary is required (minimum Rs. 1). All allowances and fixed deductions are optional and default to zero. Legacy import allowances/deductions headers remain supported. Store account numbers as text to preserve leading zeros. Login accounts, photos and documents are added afterward through Edit.

Import is atomic: the first invalid row reports its worksheet row number and rolls back every inserted teacher and counter change. Export downloads filtered and sorted teacher directory records as XLSX or PDF, including records beyond the current pagination page. Import uses its own template; the directory export is a reporting file rather than an import template.

Photos are public files on the `public` disk. Documents are stored on the private `local` disk and downloaded through a teacher/document ownership check. Documents accept PDF/JPG/PNG up to 5 MB each (10 per form submission); photos accept JPG/PNG/WebP up to 5 MB. Archiving a teacher preserves related documents, assignments, leaves and payrolls.

## Every added or changed source file

Paths below are relative to the project root.

| File | Purpose |
| --- | --- |
| `database/migrations/2026_10_01_000400_create_teachers_module.php` | Creates departments, designations, subjects, teachers with soft deletes, private document metadata, assignments, leave types/requests, salary structures, payrolls, attendance, advances and the yearly employee counter. Adds Teacher role support to users, creating a users table if this project's missing base migration left it absent. Seeds only leave types. Reverse migration preserves the users table/accounts and role column because they may predate this module. |
| `app/Models/Teacher.php` | Teacher attributes, date casts, initials accessor, soft deletes, and relationships to all teacher records. |
| `app/Models/Department.php` | Department lookup and its teachers relationship. |
| `app/Models/Designation.php` | Designation lookup and its teachers relationship. |
| `app/Models/Subject.php` | Subject lookup and its assignments relationship. |
| `app/Models/SchoolClass.php` | Eloquent mapping for existing `school_classes` and teacher assignments. |
| `app/Models/SchoolSection.php` | Eloquent mapping for existing `school_sections` and teacher assignments. |
| `app/Models/AcademicSession.php` | Eloquent mapping for existing `academic_sessions`; also the shared row lock for assignment conflict checks. |
| `app/Models/TeacherDocument.php` | Private document name/path metadata and teacher relationship. |
| `app/Models/TeacherAssignment.php` | Links teacher, class, section, subject and session; casts the class teacher flag. |
| `app/Models/LeaveType.php` | Annual entitlement, paid/unpaid flag and requests relationship. |
| `app/Models/LeaveRequest.php` | Leave dates, review timestamp and teacher/type relationships. |
| `app/Models/SalaryStructure.php` | One current basic/allowance/deduction structure per teacher. |
| `app/Models/TeacherAttendance.php` | One attendance status per teacher/date for payroll. |
| `app/Models/TeacherAdvance.php` | Advance amount, deduction month, notes and teacher relationship. |
| `app/Models/Payroll.php` | Monthly salary snapshot, payment timestamp and teacher relationship. |
| `app/Models/User.php` | Adds assignable role and teacher relationship; retains existing hashed password casting. |
| `app/Http/Requests/TeacherRequest.php` | Shared Add/Edit validation, unique formatted CNIC, salary, login, photos and documents. |
| `app/Http/Requests/AssignmentRequest.php` | Validates assignment IDs, class teacher flag and weekly periods. |
| `app/Http/Requests/ClassTeacherRequest.php` | Validates setting/clearing class teacher on an existing assignment. |
| `app/Http/Requests/LeaveRequestForm.php` | Validates applicant, leave type, inclusive dates, same-year end date and reason. |
| `app/Http/Requests/PayrollRequest.php` | Validates `Y-m` month inputs for generation and summary PDFs. |
| `app/Http/Requests/TeacherActionRequest.php` | Route-specific validation for setup, reviews, attendance, advances and import uploads. |
| `app/Services/TeacherRecords.php` | Transactional employee ID, teacher/account/salary saving and uploads; cleans new files after rollback and replaces old photos after commit. |
| `app/Services/TeacherLeaves.php` | Reserved-day balances, overlapping request prevention and generated-payroll edit locks. Uses teacher parent locks to serialize applications. |
| `app/Services/TeacherPayroll.php` | Eager-loads inputs, locks eligible teachers, calculates monthly amounts, rejects invalid batches, and saves payrolls transactionally. Immutable dates prevent month/date mutation. |
| `app/Http/Controllers/TeacherController.php` | CRUD, validated search/filter/sort query shared with exports, options/setup, eager-loaded profile, document downloads/deletion and flash feedback. |
| `app/Http/Controllers/TeacherAssignmentController.php` | Assignment directory, eager-loaded workload, duplicate/conflict checks, role updates and assignment removal. Locks academic session rows to serialize cross-teacher conflicts. |
| `app/Http/Controllers/TeacherLeaveController.php` | Monthly list/calendar, selected teacher balances, applications and final reviews with remarks. |
| `app/Http/Controllers/TeacherPayrollController.php` | Payroll list/summary, generation, payment marking, dompdf slips/report, advances and attendance with edit locks. |
| `app/Http/Controllers/TeacherTransferController.php` | Import screen/template, atomic Excel/CSV import, filtered Excel/PDF directory exports. |
| `app/Imports/TeacherImport.php` | Reads heading rows, resolves setup names, validates each teacher and identifies the first failing row. |
| `app/Exports/TeacherExport.php` | XLSX directory columns/headings with salary/login credentials excluded. |
| `routes/web.php` | Teachers resource plus assignment, leave, payroll, setup, document and transfer routes; static paths precede teacher bindings. |
| `resources/views/components/sidebar.blade.php` | Teachers dropdown with exactly four entries and active/expanded states. |
| `resources/views/teachers/index.blade.php` | Directory, searchable auto filters, sortable headers, pagination, archive actions, Import/Export/Add header and setup. |
| `resources/views/teachers/form.blade.php` | Shared Add/Edit screen with Personal, Contact, Employment, Qualification, Salary, Login account, Photo and Documents sections. |
| `resources/views/teachers/show.blade.php` | Overview, Qualification, Documents, Assigned Classes, Leaves and Salary history accessible tabs. |
| `resources/views/teachers/assignments.blade.php` | Subject assignment form, existing class teacher controls, assignment table and workload table. |
| `resources/views/teachers/leaves.blade.php` | Leave filters, annual balances, application form, review badges/remarks and month calendar. |
| `resources/views/teachers/payroll.blade.php` | Month/generation controls, paid/pending totals, salary table, slips, payments, attendance entry/list and advance entry/list. |
| `resources/views/teachers/import.blade.php` | Import guidance, template download and XLSX/CSV upload. |
| `resources/views/teachers/partials/avatar.blade.php` | Public photo or initials fallback, including missing file fallback. |
| `resources/views/teachers/partials/setup.blade.php` | Reusable lookup/leave type creation panel and Academic Setup link. |
| `resources/views/teachers/pdf/style.blade.php` | Shared dompdf-compatible report typography/table styles. |
| `resources/views/teachers/pdf/directory.blade.php` | Printable filtered teacher directory. |
| `resources/views/teachers/pdf/slip.blade.php` | One salary slip with components, status, date and signature space. |
| `resources/views/teachers/pdf/report.blade.php` | Monthly payroll report with component columns and paid/pending totals. |
| `resources/js/app.js` | Auto-submit filters with debounced search, and broken teacher photo fallback; existing navigation, tab and confirmation handlers are reused. |
| `resources/css/app.css` | Small teacher-specific layouts for filters, calendar, balances, setup and payroll forms; retains existing visual tokens. |
| `tests/Feature/TeachersModuleTest.php` | CRUD/login/documents, archived CNIC/ID sequence, screen rendering/filters, assignment conflicts/role changes, leave balance/review/calendar, payroll accounting/idempotency/payment/edit locks, transaction rollback, joining proration, import rollback and exports. |
| `docs/teachers-module.md` | This setup guide, operational rules and file reference. |

`public/build/manifest.json` and Vite's hashed JS/CSS bundles are generated by `npm run build`; edit their source files rather than generated assets. Existing `resources/views/students/partials/feedback.blade.php` and `resources/views/layouts/admin.blade.php` are reused without source changes.

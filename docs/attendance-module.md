# Attendance module

The sidebar has **Student Attendance**, **Staff Attendance**, and **Reports**. Holiday management is linked from attendance headers for administrators. Existing panels, forms, avatars, badges, sidebar collapse behavior, profile tabs and confirmation dialogs are reused.

## Setup commands

From `C:\school management system`:

```powershell
php artisan migrate
# Choose your own email; password and confirmation are prompted securely.
php artisan attendance:admin admin@school.example --name="School Admin"
npm run build
php artisan test
php artisan serve
```

Sign in at `/attendance/login`. Existing users with role `Admin` or `Administrator` can sign in directly. Teachers use the accounts created in Teachers → Add/Edit Teacher. The admin command creates a new account only; it never changes an existing account. No default admin password is installed.

Attendance now requires authentication. The rest of the pre-existing workspace retains its existing access model. Teachers can mark only class/section/session combinations present in `teacher_assignments`, and their Teacher record must be Active. Administrator marking is unrestricted. Reports and exports apply the same assignment scope; staff records and holidays are admin-only. A Teacher without assignments gets no class options. Choose the academic session explicitly; students must be Active and assigned to the selected class, section and session.

## Optional notifications and working time

Optional `.env` settings:

```dotenv
APP_TIMEZONE=Asia/Karachi
ATTENDANCE_STAFF_START_TIME=08:00
ATTENDANCE_NOTIFY_ABSENT=false
ATTENDANCE_NOTIFICATION_CHANNEL=sms
QUEUE_CONNECTION=database
```

`whatsapp` may be used as the channel label. Check **Notify parents on new Absent marks** when saving a class, or set the default above. Jobs are dispatched after transaction commit only when a status first becomes Absent or changes back to Absent. An unchanged Absent record does not dispatch another job. A queued job rechecks the current status and skips if the absence was corrected before processing. It selects `guardian_phone`, falling back to `phone`.

The gateway is a placeholder: **no SMS or WhatsApp is actually sent**. It logs an attendance ID and channel without logging the parent's phone or message. Replace `AttendanceNotificationGateway::send()` with your provider. Start a worker in a separate terminal:

```powershell
php artisan config:clear
php artisan queue:work --tries=3
```

The migrations ensure jobs/failed-jobs, database session and cache infrastructure exist on a fresh install. Existing shared infrastructure is preserved. The app timezone defaults to Asia/Karachi so future-date validation and “today” follow the school's local day; override `APP_TIMEZONE` for another school.

## Student marking

1. Choose date, class, section and academic session, then Load Students.
2. The active roster loads in numeric roll-number order. New rows default to Present; existing rows load their saved status and remarks and show Already marked.
3. Use the touch-friendly Present/Absent/Late/Leave/Half Day buttons or Mark all Present/Absent. The summary counts update immediately. Remarks are optional.
4. Save the full roster. The server rechecks permissions and active membership, rejects missing/duplicate/out-of-class student IDs, and upserts in one transaction. A changed roster requires reloading. Edits update existing records, retaining one row per student/date.

Future dates are rejected on load and save. Sundays and defined holidays show warnings but do not prevent authorized users from marking a special school day. Class/section/session are stored on each record as historical snapshots. The original `attendance_date` column is kept synchronized for compatibility; `date` is the new canonical reporting field. Old Present/Absent/Late/Leave values are normalized to lowercase. Old records are backfilled using student assignments at migration time because previous records did not store historical class/session.

## Staff marking and payroll

Load a date to see non-archived staff who are not Inactive and have joined by that date. Existing entries load for edits. Approved leave requests force Leave regardless of submitted status and clear check-in/out. Check-in after configured start time calculates late minutes and changes Present to Late. Check-out must be on the same day and at or after check-in; overnight shifts are not modeled.

Saving validates the full staff roster, locks teacher rows and upserts `staff_attendances`. It also updates the existing `teacher_attendances` records used by Payroll. Generated payroll months are locked, preserving salary snapshots. Half Day contributes 0.5 unpaid days; approved paid leave cancels deductions as before. Approved unpaid leave contributes a full unpaid day. Payroll's `unpaid_days` column now permits fractional days.

The old Payroll → Record attendance action now requires administrator authorization and mirrors changes into staff records. It still respects payroll locks and future-date validation. The old per-student attendance URL redirects authorized users to the full class editor; old single-student POSTs direct users to that editor rather than bypassing batch validation.

## Reports, percentages and profile

- Filter a date range (up to 366 days), class, section and session.
- Daily summaries group recorded statuses by date/class/section/session.
- Monthly register grids show **P** Present, **A** Absent, **L** Late, **LV** Leave, **H** Half Day, **Off** Sunday/holiday, and **−** Unmarked. Monthly tables show the percentage for the entire selected range.
- Student percentages use recorded school days only: Present/Late earn one day, Half Day half a day, Absent zero; Leave, Sundays and holidays are excluded from the denominator. Unmarked days are not silently treated as Absent. Students without eligible records have no percentage and are not in the below-75% list.
- Not fully marked today identifies active class/section/session groups with missing records, including partially marked groups. This check always uses today, even if the report range is historical.
- Admin reports include staff summaries grouped by teacher and month; class/section filters affect student data, while staff summaries use the same date range across all staff.
- Excel downloads have Daily, Register, Low attendance, Not marked today and (admin only) Staff sheets. PDF includes the same reports. Print uses the report screen with navigation and controls hidden.
- Historical records include archived students/staff. Current active students without records appear as Unmarked.

The student profile Attendance tab includes a month selector, percentage and calendar with holiday/Sunday labels. Admins and teachers assigned to the student's current class/section/session can view it. Other visitors see a sign-in/access message. The profile uses the same percentage rules as reports.

`AttendanceStats::today()` returns `total`, `marked`, `unmarked`, `counts` keyed by status, and `percentage`. Dashboard attendance and student/teacher/class totals now use actual records. Its attendance summary weights Half Day and excludes Leave; the empty state avoids division by zero. Finance and activity/event fixtures remain presentation data until those modules are implemented.

## File guide

All paths are relative to the project root.

| File | Purpose |
| --- | --- |
| `database/migrations/2026_10_01_000700_complete_attendance_module.php` | Extends/backfills student attendance with class/session/date/marker, unique/date index; adds staff attendance and holidays; backfills staff history; enables fractional payroll days and missing queue tables. Preserves shared queue tables on rollback. |
| `database/migrations/2026_10_01_000710_ensure_attendance_auth_storage.php` | Conditionally creates missing database session/cache tables, preserving shared infrastructure on rollback. |
| `app/Models/StudentAttendance.php` | Five statuses, synchronized legacy/canonical dates and student/class/section/session/marker relationships. |
| `app/Models/StaffAttendance.php` | Staff status/time/late-minute records and teacher relationship. |
| `app/Models/Holiday.php` | Holiday date ranges/descriptions and date casts. |
| `app/Models/Teacher.php` | Adds staffAttendances relationship alongside the existing payroll attendance relationship. |
| `app/Policies/AttendancePolicy.php` | Admin recognition and exact teacher assignment permission for student marking. |
| `app/Providers/AppServiceProvider.php` | Registers the class attendance policy and administrator-only staff/holiday gate. |
| `app/Http/Middleware/AttendanceAuthenticated.php` | Requires an authenticated Admin/Teacher for attendance routes; redirects guests to sign in. |
| `app/Http/Requests/AttendanceLoginRequest.php` | Login email/password validation. |
| `app/Http/Requests/StudentAttendanceBatchRequest.php` | Date, class/session IDs, unique student rows, statuses, remarks and optional notification validation. |
| `app/Http/Requests/StaffAttendanceRequest.php` | Date/unique staff rows/statuses, check-in/out and remarks validation. |
| `app/Http/Requests/HolidayRequest.php` | Holiday name, description and valid date-range validation. |
| `app/Http/Requests/AttendanceReportRequest.php` | Validates/defaults report dates and filters, limits date range, blocks future dates. |
| `app/Services/AttendanceRecords.php` | Scoped options, numeric roster sorting, Sunday/holiday warnings, transactional student/staff upserts, roster checks and payroll synchronization. |
| `app/Services/AttendanceReports.php` | Eager-loaded, permission-scoped reports; register, percentage, low attendance, missing-today and staff monthly aggregation; profile calendar. |
| `app/Services/AttendanceStats.php` | Today's real attendance totals/status counts/weighted percentage for dashboard integration. |
| `app/Services/AttendanceNotificationGateway.php` | Replaceable SMS/WhatsApp placeholder with minimal logging. |
| `app/Jobs/SendAbsenceNotification.php` | Queued parent notification that rechecks the current absence and chooses guardian contact. |
| `app/Http/Controllers/AttendanceLoginController.php` | Session login/logout, five-attempt rate limiting and role validation. |
| `app/Console/Commands/CreateAttendanceAdmin.php` | Creates a new Admin with an interactively prompted, confirmed, hashed password. |
| `app/Http/Controllers/AttendanceController.php` | Student/staff mark screens, permission checks, transactional saves and flash feedback. |
| `app/Http/Controllers/HolidayController.php` | Admin-only holiday CRUD and flash messages. |
| `app/Http/Controllers/AttendanceReportController.php` | Scoped report rendering, Laravel Excel downloads and dompdf reports. |
| `app/Exports/AttendanceReportExport.php` | Multi-sheet workbook for the filtered/scoped reports. |
| `app/Http/Controllers/StudentAttendanceController.php` | Safe compatibility redirects from old per-student attendance routes. |
| `app/Http/Controllers/StudentController.php` | Authorized attendance month/profile data for the student view. |
| `app/Http/Controllers/DashboardController.php` | Uses AttendanceStats and real entity totals while retaining unrelated fixtures. |
| `app/Http/Controllers/TeacherPayrollController.php` | Authorizes the legacy attendance action and mirrors it into staff records. |
| `app/Http/Requests/TeacherActionRequest.php` | Adds future-date validation to the legacy payroll attendance action. |
| `app/Services/TeacherPayroll.php` | Recognizes Half Day and calculates fractional unpaid days/deductions. |
| `config/attendance.php` | Notification defaults/channel and expected staff start time. |
| `config/app.php` | Configurable school timezone, default Asia/Karachi. |
| `routes/web.php` | Sign-in/logout, protected student/staff/report/export/holiday endpoints. |
| `resources/views/components/sidebar.blade.php` | Attendance dropdown with exactly three sub-items, using existing icon-rail behavior. |
| `resources/views/attendance/login.blade.php` | Sign-in form styled with existing components. |
| `resources/views/attendance/students.blade.php` | Class/date/session selection, existing marks, status buttons, bulk buttons, summary and remarks. |
| `resources/views/attendance/staff.blade.php` | Staff date selection, automatic approved leave, status/time fields and existing late minutes. |
| `resources/views/attendance/holidays.blade.php` | Holiday list/add/edit/delete controls. |
| `resources/views/attendance/reports.blade.php` | Filters, Excel/PDF/print actions and report content. |
| `resources/views/attendance/partials/header.blade.php` | Shared page header, auth controls, holiday link, feedback and closure warnings. |
| `resources/views/attendance/partials/holiday-fields.blade.php` | Reusable holiday form fields. |
| `resources/views/attendance/partials/report-data.blade.php` | Shared daily/register/percentage/low/missing/staff report tables for screen and PDF. |
| `resources/views/attendance/partials/profile.blade.php` | Student profile percentage/month/calendar with holiday labels and access messaging. |
| `resources/views/attendance/pdf/report.blade.php` | Landscape dompdf layout for shared report data. |
| `resources/views/students/show.blade.php` | Replaces the Attendance placeholder with the profile partial. |
| `resources/views/dashboard/index.blade.php` | Live attendance percentage/rows, Unmarked counts and safe empty-state division. |
| `resources/js/app.js` | Live marking summary, mark-all actions and print action; reuses existing profile/sidebar handlers. |
| `resources/css/app.css` | Attendance status buttons, responsive mark rows, summary/warning/report/print styles. |
| `tests/Feature/AttendanceModuleTest.php` | Auth/roles, all screens, teacher scope, upserts/roster/future checks, queue transitions, holidays, reports/exports, percentages/calendar, staff lateness/leave and payroll locks. |
| `tests/Feature/TeachersModuleTest.php` | Updates the legacy payroll attendance test for admin access and a valid non-future date. |
| `tests/Feature/ExampleTest.php` | Migrates the test DB because the dashboard now queries real tables. |
| `docs/attendance-module.md` | This setup, behavior and file guide. |

Vite's manifest and hashed bundles in `public/build` are generated by the build command. Existing student and teacher records are preserved; the migrations seed no attendance or credentials.

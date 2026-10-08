# Teacher panel — Phase 1

The teacher panel lives entirely under `/teacher/*`, uses its own layout/sidebar/topbar, and shares only the existing generic stylesheet, JavaScript controls, icons, stat-card component and avatar partial. The admin layout, sidebar, topbar and dashboard Blade files were not edited. Existing admin routes now require authentication and Spatie `Admin` or `Principal`; teacher routes require `Teacher`. Unauthorized signed-in users receive 403.

## Setup and login

Spatie Laravel Permission 6.25.0 is installed and locked in Composer. Its published configuration and migrations create permissions, roles and pivots. A follow-up migration preserves existing accounts/passwords and maps `users.role` values Admin/Administrator, Principal and Teacher into Spatie roles. The existing user field remains for compatibility. Creating/changing an account's legacy role synchronizes its Spatie role, so the existing teacher creation form and admin creation command continue to work.

```powershell
composer install
php artisan migrate
php artisan db:seed --class=TeacherPanelSeeder
php artisan permission:cache-reset
php artisan cache:clear
npm run build
php artisan test
php artisan serve
```

Migrations and the dedicated teacher seeder were already run in this workspace. Use the dedicated seeder rather than reseeding all modules. It creates a Teacher demo account, links a teacher record, and creates two assignments and six students. It uses a dedicated `Teacher Demo` section in Class 1 and Class 2 with a demo English subject to avoid changing real subject assignments. Re-running does not duplicate records or reset an existing password. It refuses to take over a non-teacher account using the demo email.

Sign in at `/attendance/login`, the existing shared login endpoint:

| Account | Email | Password | Destination |
| --- | --- | --- | --- |
| Teacher demo | `teacher@test.com` | `password` on first creation | `/teacher/dashboard` |
| Existing Admin/Principal | Your existing account | Existing password is preserved | `/dashboard` |

The existing local Admin account previously created for this workspace is `admin@school.example`; its password was not changed. If an Admin account needs to be created, the existing `php artisan attendance:admin your-email@example.com --name="School Admin"` command prompts for a password and creates a new account only.

## Behavior and scope

- Dashboard cards count distinct assigned class/section/session groups, groups with incomplete attendance today, zero pending quizzes until the quiz module is built, and the logged-in teacher's remaining annual leave days. Multiple subject assignments in the same class do not double-count the top class or attendance cards.
- Class cards retain subject-level assignments. They show active student counts, complete versus unmarked/partial attendance today, a class teacher badge and Attendance/Quiz/Progress/Students links.
- All class, student and attendance routes authorize the `TeacherAssignment` first. Both reads and saves use its actual class/section/session and the authenticated teacher's record. A forged assignment ID belonging to another teacher returns 403. Form submissions cannot choose a different class or session.
- Rosters include active, non-deleted students in the assignment's exact session/class/section, ordered numerically by roll number. Students pages are read-only, paginated, and include photos/initials, parent contact and call/WhatsApp links. They never query fees or salary.
- Attendance uses the existing `student_attendances` table and unique `(student_id, date)` constraint from `2026_10_01_000700_complete_attendance_module.php`; no duplicate table is created. It records canonical date/session/class/section/marked_by and preserves the legacy `attendance_date` field. The teacher screen offers present/absent/late/leave. Existing half-day records remain visible in the monthly register.
- Attendance defaults to Present, loads already marked values, provides large toggle controls, remarks and a live summary. Both GET and POST block future dates. Holiday/Sunday warnings use the existing holidays service. Saving validates the complete active roster, locks rows and upserts in a DB transaction. Duplicate submissions update existing records. The monthly register is paginated by student and scoped to the same assignment.
- Low attendance means `(present + late) / marked days` below 75%, limited to the assigned session/class/section and roster. Unmarked days are excluded. Leave days are included in marked days. No attendance data produces an empty state.
- Dashboard results use a teacher-specific 30-second cache, local date and the existing dashboard data revision. Student/attendance changes invalidate the revision; attendance saves also forget the teacher cache. Other assignment/leave changes become visible within 30 seconds.
- Notices use the existing school-wide `events` records (upcoming event/meeting/exam/holiday announcements), limited to five recent publications. There is no dedicated notices/audience table yet; this is explicitly a school-wide announcement source. Empty data shows “No notices available.”
- Leaves show only the authenticated teacher's requests and remarks; My Profile shows only their basic details. Leave application, quizzes/results and timetable editing belong to later phases. Pending quizzes is zero with an explanatory subline. Student Progress links to the scoped monthly attendance register; academic progress remains a later phase.

## Files created

| File | Purpose |
| --- | --- |
| `config/permission.php` | Published Spatie role/permission configuration. |
| `database/migrations/2026_10_02_101528_create_permission_tables.php` | Creates Spatie permission, role and pivot tables. |
| `database/migrations/2026_10_02_101600_migrate_existing_user_roles.php` | Creates Admin/Principal/Teacher roles and maps existing accounts without changing passwords. |
| `routes/teacher.php` | Separate named teacher route file. |
| `app/Policies/TeacherAssignmentPolicy.php` | Restricts assignment viewing and marking to its active teacher owner. |
| `app/Http/Requests/TeacherAttendanceRequest.php` | Assignment policy authorization and date/status/remarks/roster field validation. |
| `app/Services/TeacherPanelService.php` | Teacher-scoped assignments, rosters, counts, cache, low attendance, leave balances and announcements. |
| `app/Http/Controllers/Teacher/DashboardController.php` | Renders the teacher dashboard from the scoped service. |
| `app/Http/Controllers/Teacher/ClassController.php` | Assigned classes and read-only paginated students. |
| `app/Http/Controllers/Teacher/AttendanceController.php` | Authorized marking, transactional saves and monthly register. |
| `app/Http/Controllers/Teacher/PanelController.php` | Own profile/leaves/notices and scoped next-phase destinations. |
| `resources/views/layouts/teacher.blade.php` | Independent teacher shell with feedback and existing visual styles. |
| `resources/views/teacher/partials/sidebar.blade.php` | Exactly nine teacher menu items, with no admin navigation. |
| `resources/views/teacher/partials/topbar.blade.php` | Independent name/role/search/hamburger/sign-out topbar. |
| `resources/views/teacher/partials/header.blade.php` | Teacher page headings and live local date. |
| `resources/views/teacher/partials/classes.blade.php` | Assignment cards and their authorized action links. |
| `resources/views/teacher/partials/notices.blade.php` | School-wide announcements and empty state. |
| `resources/views/teacher/dashboard.blade.php` | Four stats, assignment cards, low attendance and notices. |
| `resources/views/teacher/classes/index.blade.php` | My Classes screen. |
| `resources/views/teacher/classes/students.blade.php` | Read-only student/contact table without fee information. |
| `resources/views/teacher/attendance/index.blade.php` | Assigned-class attendance selection. |
| `resources/views/teacher/attendance/mark.blade.php` | Date, toggles, summary, remarks and attendance form. |
| `resources/views/teacher/attendance/register.blade.php` | Monthly P/A/L/LV register and pagination. |
| `resources/views/teacher/panel.blade.php` | Own profile, leave requests, notices and later-phase empty states. |
| `database/seeders/TeacherPanelSeeder.php` | Idempotent demo Teacher account, two assignments and six students. |
| `tests/Feature/TeacherPanelTest.php` | Role separation, redirects, assignment access, roster/date protection, upsert, DB uniqueness, dashboard data and empty states. |
| `storage/app/verify-teacher-panel.mjs` | Local headless browser verification of desktop/mobile layouts, menu and attendance controls. |
| `docs/teacher-panel-phase-1.md` | Setup, behavior, file guide and manual testing instructions. |

## Files changed

| File | Purpose |
| --- | --- |
| `composer.json`, `composer.lock` | Adds and locks Spatie Laravel Permission. |
| `bootstrap/app.php` | Registers teacher routes, Spatie role middleware alias and guest login redirect. |
| `routes/web.php` | Protects existing admin routes, provides `/dashboard`, keeps shared login/logout outside the admin role group. |
| `app/Models/User.php` | Adds HasRoles and compatibility synchronization from existing user role changes. |
| `app/Policies/AttendancePolicy.php` | Uses Spatie Admin/Principal/Teacher checks while retaining assignment enforcement. |
| `app/Providers/AppServiceProvider.php` | Registers the teacher assignment policy. |
| `app/Http/Controllers/AttendanceLoginController.php` | Accepts allowed Spatie roles and redirects to the correct panel after login. |
| `tests/Feature/AdmissionsPromotionTest.php` | Signs in as Admin for protected admin workflows. |
| `tests/Feature/StudentTest.php` | Signs in as Admin for protected student workflows. |
| `tests/Feature/StudentModuleTest.php` | Signs in as Admin for protected student workflows. |
| `tests/Feature/SubjectsModuleTest.php` | Signs in as Admin for protected subject workflows. |
| `tests/Feature/TeachersModuleTest.php` | Signs in as Admin for protected teacher management workflows. |
| `tests/Feature/DashboardTest.php` | Signs in as Admin and explicitly signs out for guest access checks. |
| `tests/Feature/AttendanceModuleTest.php` | Updates login destination and tests teacher attendance through its separate routes. |
| `tests/Feature/ExampleTest.php` | Verifies guests redirect to login rather than opening an admin dashboard. |
| `public/build/manifest.json`, `public/build/assets/*` | Rebuilds shared UI assets to include the new Blade utility classes. |

## Manual testing

1. Sign in as Admin at `/attendance/login`. Confirm redirect to `/dashboard` and the existing admin dashboard/sidebar. Open `/teacher/dashboard` and `/teacher/classes`: both must return 403. Admin management of students/teachers/academic setup remains accessible.
2. Sign out and log in as `teacher@test.com` / `password`. Confirm redirect to `/teacher/dashboard`, exactly nine teacher sidebar items, two demo classes and real student counts. Open `/dashboard`, `/students`, `/teachers`, `/fees/setup/types`, `/attendance/students` or `/academic-setup`: each must return 403.
3. Open My Classes → Students. Confirm three demo students per class, roll ordering, father/phone and call/WhatsApp links. Upload a photo through admin student editing; it should appear here, with initials if missing. There must be no fee data or student edit buttons.
4. Open Attendance for a demo class. Confirm Present defaults and live counts. Mark one student Absent, add remarks and save. Reload and confirm the selected value and remarks survive; save again with a different status, and verify only one database row remains per student/date. The dashboard's pending count decreases when all active students are marked.
5. Try tomorrow's date in the URL/form: validation rejects it. Try a Sunday/holiday: a warning appears. Open Monthly register and change the month; saved status letters appear and unmarked days show —.
6. As Admin create an assignment for another teacher; while signed in as the demo teacher, substitute that assignment ID in `/teacher/classes/{assignment}/students`, `/teacher/attendance/{assignment}`, or `/teacher/attendance/{assignment}/register`: each must return 403. Requests with another class's student IDs are rejected before writing anything.
7. Mark several past days Absent; the student appears below 75% on their teacher's dashboard. Add an upcoming school-wide event through the Admin Notices/events CRUD; it appears in the teacher notices area within 30 seconds.
8. Resize to mobile and use the hamburger. The teacher sidebar collapses to an icon rail and expands to its own navigation; attendance buttons and tables remain usable without page-wide horizontal overflow.

Focused automated checks: `php artisan test --filter=TeacherPanelTest`. Run `php artisan test` for all existing modules and the new panel.

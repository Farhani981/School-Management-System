# Teacher Dashboard redesign

The dashboard uses real assigned class sections, grouped by class/section/session so multiple subjects share one daily register. The hero, Needs you counts, and sidebar badge reflect those registers. Quick mark loads the real active roster, supports Present/Absent/Late and All present, and saves through the existing teacher.attendance.store route. JSON responses update counts and progress without navigating. Validation, assignment ownership, complete-roster checking, transactions, and normal HTML form redirects remain in place.

Files changed:
- resources/views/teacher/dashboard.blade.php: hero, assigned-class/optional-period rows, inline attendance, Needs you, latest notice.
- resources/css/teacher-dashboard.css and resources/js/teacher-dashboard.js: scoped design and vanilla interactions, 30-second clock, midnight guard, keyboard controls, dark mode and reduced motion.
- resources/css/app.css and resources/js/app.js: import dashboard assets; dashboard-only 980px navigation breakpoint and mobile focus isolation.
- resources/views/layouts/teacher.blade.php and teacher/partials/{topbar,sidebar}.blade.php: scoped page class, Google Fonts, attendance badge. Teacher pages use the original shared x-topbar component, matching Admin. Existing navigation and routes preserved.
- app/Services/TeacherPanelService.php: real registers, full low-attendance count, latest notice, optional period adapter, versioned cache.
- app/Http/Controllers/Teacher/{DashboardController,AttendanceController}.php: attendance warnings and JSON response on the existing POST endpoint.
- tests/Feature/TeacherPanelTest.php and tests/browser/teacher-dashboard.mjs: authorization, roster validation, counts, optional timetable rendering, deduplication, responsive layout and AJAX interactions.

## Why Leave Balance was 387

The old TeacherPanelService::dashboard() loop summed TeacherLeaves::balance() for every leave type, including unpaid leave. The migration seeds Casual = 12, Sick = 10 and Unpaid = 365: 12 + 10 + 365 = 387. This was aggregation of different leave entitlements, not 387 paid days or a date arithmetic failure. The dashboard no longer calculates or displays this total. Leave-type balances remain in their module; the seeded data and existing leave rules are unchanged.

## Missing modules and explicit integration points

- Timetable: no timetable table/model exists. todayPeriods(User) returns an empty array. The page shows My classes today and the configuration note. Connect that method to the future timetable query, returning today's teacher-owned periods with assignment_id (null for a free period), starts_at and ends_at (ISO timestamps with offsets). Nonempty period data automatically switches the view, scopes the hero register counts to scheduled classes, and enables countdowns and Now badges. Start/end times are never inferred from weekly_periods.
- Quizzes: the existing row Quiz link points to the existing placeholder module. No quiz count is fabricated.
- Notice/leave/quiz create forms: no teacher create routes exist. The quick-actions row is hidden. The template contains a TODO and route-existence checks for teacher.notices.create, teacher.leaves.create and teacher.quizzes.create. No leave or quiz pending item appears in Needs you.
- Notices: the existing school calendar announcements are used. Per-teacher read receipts do not exist, so no unread dot is fabricated.
- Approved HTML prototype: not attached during this implementation. The supplied design-system and interaction requirements were used.

## Validation

TeacherPanel, TeachersModule and AttendanceModule: 31 tests, 316 assertions passed. Browser checks use a separate SQLite fixture database and verify widths from 320 to 1440, hidden sidebar under 980, keyboard dismissal, incomplete selection disabling Save, Present/Absent/Late summary, failed save preserving selections, two real AJAX saves, updated pill/hero/ring/badge, no navigation, dark mode and reduced motion.

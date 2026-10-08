# Leaves — Phase 1 review

Implemented Phase 1 only. Migration has been applied to the workspace database. Review at `/leaves/types` and `/leaves/balances` with an Admin or Principal account. Configure quotas, select a session, then create balances. Balances are intentionally not initialized automatically.

## Investigation of 387

The original migration `database/migrations/2026_10_01_000400_create_teachers_module.php` seeds Casual 12, Sick 10, and Unpaid 365. The workspace database confirmed these values, one current session (2026-2027), and zero leave requests before implementation. Their sum is 387. This supports the reported number being an aggregate of seeded allowances, including unpaid leave, rather than a meaningful paid balance. No current dashboard template/service renders 387 or a Leave Balance widget, so the historical rendering path cannot be proved from the present source. The current dashboard already hides leave information and conditionally includes Apply leave only when its route exists.

The old TeacherLeaves service calculates calendar-year calendar days and groups pending with approved. It remains operational for legacy admin routes during Phase 1; it is not the new balance calculation. Phase 2/3 must replace that workflow and keep date/status compatibility with payroll and attendance. Do not consider the legacy submission/review screens the completed new module.

## Decisions and data gaps

- Existing tables, authentication, roles, route names, sidebar and topbar retained. Added routes inherit the existing Admin/Principal middleware.
- Existing quotas preserved as decimal annual_quota. Emergency seeded at zero because no entitlement was supplied. Zero means no quota limit under the supplied specification, so review settings before teacher applications go live.
- Legacy Unpaid type preserved. Its 365 is not a paid entitlement; the UI labels unpaid and zero-quota types as having no balance limit.
- New session balances snapshot configured quotas. Changing a type does not silently rewrite existing entitlements. Adjustments require a reason and record actor, before/after total, and time.
- Fresh initialization is repeatable and does not overwrite existing balances. Optional carry-forward uses nonnegative total minus used minus pending for paid, quota-limited types. Existing source balances stay unchanged. Active teachers only; inactive/soft-deleted teachers excluded from new balances.
- Sessions have no date boundaries. Historical request session_id and days stay null rather than guessing or retroactively recosting requests. Before adopting existing requests, explicitly reconcile their sessions, working days, decisions, logs and approved usage. Bulk initialization blocks target sessions that already contain requests.
- Legacy capitalized statuses retained; pending aggregation supports both cases. Canonical status transition logic and synchronized legacy/new date fields belong to Phase 2/3.
- Decimal quotas and adjustments accept half-day increments. No type deletion endpoint is exposed, including for unused types.
- Added separate leave_balance_logs because balance adjustments have no request_id. Recent session audit is visible on the balances page.
- Timetable module exists; substitution integration is reserved for Phase 5. Teacher/dashboard/attendance changes are reserved for their requested phases.
- Leave styling supports OS dark preference and explicit .dark/data-theme=dark ancestors. Fonts use the same Google Fonts families as the teacher layout. No visual browser inspection was performed.

## Files added or changed

- database/migrations/2026_10_06_000100_extend_leaves.php
- app/Models/LeaveType.php
- app/Models/LeaveRequest.php
- app/Models/LeaveBalance.php
- app/Models/LeaveRequestLog.php
- app/Services/LeaveBalances.php
- app/Http/Controllers/LeaveSetupController.php
- routes/web.php
- resources/views/components/sidebar.blade.php
- resources/views/layouts/admin.blade.php
- resources/views/leaves/types.blade.php
- resources/views/leaves/type-fields.blade.php
- resources/views/leaves/balances.blade.php
- resources/views/leaves/messages.blade.php
- resources/css/app.css
- resources/css/leaves.css
- tests/Feature/LeaveSetupTest.php
- docs/leaves-phase-1.md
- public/build/manifest.json
- public/build/assets/app-CNA6tcnz.css
- public/build/assets/app-BnAHgtXn.js
- public/build/assets/auth-BcLZlXpV.css
- public/build/assets/auth-CWKGdi1r.js
- Vite clears superseded generated assets on build.

Workspace database schema updated by migration; no leave requests, approvals, or teacher balance allocations created. Blade compiled caches regenerated. This workspace has no Git repository, so file tracking is recorded here.

## Validation

- Phase 1 feature tests: 5 passed, 43 assertions.
- Migration applied successfully; routes registered; Blade templates compiled.
- Production asset build passed. Vite reported a pre-existing unresolved auth-school-campus.jpg reference.
- Combined LeaveSetupTest, TeachersModuleTest, AttendanceModuleTest and TeacherPanelTest: 38 passed, 375 assertions.

Stop here for review before Phase 2.

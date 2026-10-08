# Fees Phase 1: Fee Setup

Fee Setup reuses the existing cards, forms, currency inputs, status badges, tables, profile tabs and confirmation dialogs. The Fees sidebar has exactly five children: Fee Setup, Vouchers, Collection, Defaulters and Reports. The last four are coming-soon pages; no vouchers, collections or arrears are generated in Phase 1.

## Run

From the project directory:

```powershell
php artisan migrate
php artisan db:seed --class=FeeSetupSeeder
php artisan cache:clear
php artisan view:clear
npm run build
php artisan test
```

If PHP is not in PATH, use `C:\xampp\php\php.exe` instead of `php`. No new package or storage link is needed. Use the dedicated seeder on an existing installation; the root DatabaseSeeder also creates a demo user and is intended for initial setup.

The seeder creates six fee types without overwriting existing types or reactivating archived/inactive ones. For the current academic session it inserts missing Tuition and Exam structures for existing classes: Class 1 Tuition Rs. 1,500 / Exam Rs. 500, increasing evenly to Class 10 Tuition Rs. 3,500 / Exam Rs. 1,000. Existing custom amounts are preserved on reruns. Early years use Class 1 amounts. If no session is current, it seeds types only and displays a warning: select a current session under Settings / Academic Setup, then rerun FeeSetupSeeder. It does not create students or discounts, or invent activity entries for seed data.

## Access and screens

Sign in with the existing Admin account. `FeesAuthenticated` checks login; the named `fees.manage` Gate currently allows Admin/Administrator and can be extended later without rewriting controllers. All Fee Setup, lookup, preview, override and placeholder routes require that Gate. Student profile/edit pages show fee setup only to authorized users. No additional login credentials are created.

- **Fee Types:** search, paginate, add/edit, activate/deactivate, archive unused types. Deletion is blocked by any structure, student discount or override. Future voucher references in `fee_voucher_items`, `voucher_items` or a `fee_vouchers.fee_type_id` column are checked when present.
- **Class-wise Structure:** select session/class, enter all active type amounts and save atomically. Empty inputs become zero. The selected structure total includes all frequencies; the overview includes monthly types only. Copy replaces active destination rows and retains inactive rows. Empty sources and identical source/destination are rejected. Changed/incomplete/tampered type selections are rejected before writing.
- **Discounts:** add/edit fixed Rs. or percent discounts; percent is limited to 100. Search students by name, full name, admission number or phone; results are capped at 20. Assign an active discount to all fees or a single active fee type, with validity dates. Overlapping assignments of the same discount/scope are blocked, including overlap between all-fees and specific-fee scopes. Remove assignments or deactivate a discount before deleting a used discount.
- **Student special amount:** open Student Edit or Profile > Fees. Choose a type, amount and reason. Saving replaces that student's existing override for the type. Zero is an explicit waiver. Removing it restores the class amount. Override removal is scoped to its student.
- **Fee Settings:** due day 1–31, none/fixed/per-day late fine, amount, grace days, cap, prefixes and sibling rule. Days beyond the month's length clamp to the final day. Zero cap means no cap for Phase 2; fine and arrears remain zero now. Prefixes normalize to uppercase, start with a letter and allow letters, digits and hyphens, up to 10 characters.
- **Preview:** Student Profile/Edit > Fee Preview; choose month/year. It shows applicable items, overridden amounts, discounts, subtotal and total. Preview does not create a billing record or alter Dashboard pending fees.

## Calculation contract

```php
$result = app(\App\Services\FeeCalculatorService::class)
    ->calculateForStudent($student, 10, 2026);
// items, subtotal, discount, fine, arrears, total, billing_date, provisional
```

Each item includes `fee_type_id`, `fee_type` (name), `frequency`, `base_amount`, `amount` after override, `override_reason`, `discount` and `net_amount`. All persisted amounts are decimal(10,2). Arithmetic uses integer paisa, rounds percentage discounts to the nearest paisa and caps deductions at eligible remaining balances. Returned money values are numeric Rs. values.

1. Load active, non-archived fee structures for the student's actual class_id and academic_session_id, with fee types eager-loaded. No assignment means an empty zero estimate.
2. Monthly types apply every month; quarterly types apply in March, June, September and December. Yearly and one-time types call `alreadyBilled()` before being included.
3. Apply student amount overrides, including zero. Subtotal is the sum after overrides, before discounts.
4. Evaluate discount validity on that month's configured due date, inclusively. Apply active assignments in stable ID order to the remaining amounts. Specific-type discounts affect only that line; fixed all-fee discounts are applied once and distributed proportionally, with leftover paisa allocated by largest remainder. Percent discounts are calculated per eligible remaining line. Distinct discounts can stack, but cannot produce negative lines or totals.
5. If enabled, apply the sibling rule after assigned discounts. Active siblings in the same session match the existing student family rule: Family ID or normalized father name plus the same phone. The earliest admitted active child pays full fee; later children qualify, ordered by admission date then ID. Inactive/archived siblings do not establish eligibility. This is a discount from the second child onward, not an increasing rate for third/fourth children.
6. Return fine = 0, arrears = 0 and total = subtotal minus capped discounts.

**Phase 2 hook:** `FeeCalculatorService::alreadyBilled()` is intentionally a protected TODO hook returning false. No voucher history exists yet, so yearly/one-time lines are provisional and repeated previews can show them again. Phase 2 must check persisted non-cancelled voucher items: one-time once per student, yearly once per student/calendar year, and enforce billing uniqueness in the voucher-generation transaction. A preview must never consume a fee or record it as billed. The unit suite exercises the hook using a subclass that reports these types as already billed.

Phase 2 must also snapshot rates/discounts in vouchers, integrate cancellation/refund rules, calculate late fines/arrears, and prevent changes to paid voucher history. Fee settings are configuration, not current financial balances. The Dashboard continues to show Rs. 0 until real voucher/payment tables are added.

## Files created

| File | Purpose |
| --- | --- |
| `database/migrations/2026_10_02_000900_create_fee_setup.php` | Creates the six setup tables, indexes, foreign keys, decimal amounts and singleton settings defaults. |
| `app/Models/FeeType.php` | Frequency labels, active scope, soft deletes and fee relationships. |
| `app/Models/FeeStructure.php` | Session/class/type relationships and amount cast. |
| `app/Models/Discount.php` | Fixed/percent definition, active cast and assignment relationship. |
| `app/Models/StudentDiscount.php` | Student/type/discount relationships and validity dates. |
| `app/Models/StudentFeeOverride.php` | Student/type relationships, special amount and reason. |
| `app/Models/FeeSetting.php` | Singleton configuration and amount/boolean casts. |
| `app/Http/Middleware/FeesAuthenticated.php` | Login check without hardcoding roles; authorization belongs to the Gate. |
| `app/Http/Requests/FeeTypeRequest.php` | Unique names, allowed frequencies and active flag. |
| `app/Http/Requests/FeeStructureRequest.php` | Destination and nonnegative, two-decimal amount validation. |
| `app/Http/Requests/FeeStructureCopyRequest.php` | Valid source/destination IDs for copying. |
| `app/Http/Requests/DiscountRequest.php` | Discount name/type/value/status rules and percent maximum. |
| `app/Http/Requests/AssignStudentDiscountRequest.php` | Active lookup records and chronological validity dates. |
| `app/Http/Requests/StudentFeeOverrideRequest.php` | Active type, special amount and required reason. |
| `app/Http/Requests/FeeSettingsRequest.php` | Due day, fine, prefix and sibling rule validation. |
| `app/Services/FeeSetupService.php` | Transactional structure save/copy, overlap protection, delete-use checks and activity logging. |
| `app/Services/FeeCalculatorService.php` | Provisional calculation, frequency hook, overrides, discounts, siblings and paisa rounding. |
| `app/Observers/FeeSetupObserver.php` | Logs discount create/update/delete when the activities table exists. |
| `app/Http/Controllers/FeeSetupController.php` | Setup redirect, placeholder pages, bounded student lookup and preview. |
| `app/Http/Controllers/FeeTypeController.php` | Searchable fee type CRUD and protected archive. |
| `app/Http/Controllers/FeeStructureController.php` | Structure editor, grouped monthly overview, save and copy actions. |
| `app/Http/Controllers/DiscountController.php` | Discount CRUD and eager-loaded student assignments. |
| `app/Http/Controllers/FeeSettingsController.php` | Singleton settings edit/save with audit entry. |
| `app/Http/Controllers/StudentFeeOverrideController.php` | Save/remove overrides with student ownership checks. |
| `database/seeders/FeeSetupSeeder.php` | Repeatable fee types and missing sample structures for the current session. |
| `resources/views/fees/partials/header.blade.php` | Shared Fee Setup tabs and feedback using existing styling. |
| `resources/views/fees/partials/delete-dialog.blade.php` | Reusable confirmation dialog for removal actions. |
| `resources/views/fees/types/index.blade.php` | Fee type table, search, badges and actions. |
| `resources/views/fees/types/form.blade.php` | Fee type add/edit form. |
| `resources/views/fees/structures.blade.php` | Session/class selector, amounts, live total, copy and monthly overview. |
| `resources/views/fees/discounts/index.blade.php` | Discount table, student lookup, assignment form and assignment table. |
| `resources/views/fees/discounts/form.blade.php` | Fixed/percent discount add/edit form. |
| `resources/views/fees/settings.blade.php` | Due/fine/prefix and sibling settings forms. |
| `resources/views/fees/coming-soon.blade.php` | The four later-phase placeholder pages. |
| `resources/views/fees/partials/calculation.blade.php` | Shared provisional item table and totals. |
| `resources/views/fees/preview.blade.php` | Month/year preview screen. |
| `resources/views/fees/partials/student.blade.php` | Student override editor/table and current-month preview. |
| `tests/Concerns/CreatesFeeFixtures.php` | Shared isolated fee fixtures for tests. |
| `tests/Unit/FeeCalculatorServiceTest.php` | Direct service tests with isolated SQLite data, including the four requested calculator cases. |
| `tests/Feature/FeeSetupTest.php` | Authorization, rendering, sidebar, CRUD, copy, validation, overrides, settings and seeding checks. |
| `docs/fees-phase-1.md` | This setup and file guide. |
| `storage/app/verify-fee-setup.mjs` | Local headless-browser check for sidebar state, page rendering, live total, lookup, dialogs and responsive widths; password is supplied through an environment variable, not stored in this file. |

## Files changed

| File | Purpose |
| --- | --- |
| `app/Models/Student.php` | Adds fee discount and override relationships. |
| `app/Models/SchoolClass.php` | Adds class fee structure relationship. |
| `app/Models/AcademicSession.php` | Adds session fee structure relationship. |
| `app/Providers/AppServiceProvider.php` | Registers fees.manage Gate and discount observer. |
| `app/Services/DashboardStatsService.php` | Uses wallet icons for fee setup/discount activity types. |
| `app/Http/Controllers/StudentController.php` | Loads fee setup only for authorized student profile/edit requests. |
| `resources/views/students/edit.blade.php` | Includes special amount section outside the main student form. |
| `resources/views/students/show.blade.php` | Connects the existing Fees profile tab to Fee Setup. |
| `resources/views/components/sidebar.blade.php` | Adds five Fee children with active/expanded state. |
| `resources/js/app.js` | Live structure total, removal dialog and debounced student lookup; scopes academic quick-add behavior to actual quick-add controls. |
| `resources/css/app.css` | Keeps Fee Setup checkboxes inline using the existing color and size. |
| `routes/web.php` | Adds named permission-protected setup, preview, override and placeholder routes. |
| `database/seeders/DatabaseSeeder.php` | Includes FeeSetupSeeder in initial installation seeding. |
| `public/build/manifest.json`, `public/build/assets/*` | Vite-generated frontend assets. |

## Manual checks

1. Sign in as Admin and open **Fees > Fee Setup**. Confirm exactly five sidebar children and that Fees remains expanded on each child page. The other four show Coming soon.
2. Search the six seeded types, add a test type, edit its frequency/status, and delete it while unused. Try deleting Tuition after it has a structure: verify the clear blocked message. Deactivate a type and verify it disappears from the editor/calculator.
3. Open **Class-wise Structure**, choose current session/Class 1 and save amounts. Check the live total and monthly overview. Copy Class 1 to Class 2 or another session. Enter a negative amount and confirm validation leaves all previous rows unchanged.
4. Create a fixed discount of Rs. 100 and a percent discount of 10%. Search/select a student, assign one to Tuition or all fees, and choose dates covering the month's due date. Verify the preview. Duplicate overlapping assignments must be rejected.
5. On **Student Edit** or **Profile > Fees**, set Tuition to Rs. 900 with a reason. Preview should use 900 before discounts. Set zero to waive it; remove the override to restore the class rate.
6. Enable a 10% sibling discount in **Fee Settings**. Use two active same-session siblings with matching Family ID or father/phone. The first admitted child has no automatic sibling discount; the second does. Check a fixed rule too.
7. Preview October and December. Exam Fee appears in December, while monthly fees appear in both. Fine/arrears remain zero; one-time/yearly amounts are explicitly provisional.
8. Change a fee structure or discount and check **Dashboard > Recent Activities**. Setup changes appear with wallet icons. Pending fees remain zero because no voucher is created.
9. Run `php artisan test --filter="FeeCalculatorServiceTest|FeeSetupTest"` for focused tests; tests use isolated SQLite and do not modify the real database.

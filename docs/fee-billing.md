# Fee billing

Admin and Principal users can manage Fees. Teachers and guests cannot access the ledger.

1. Configure fee types, class/session fee structures, discounts and fee settings in Fee Setup.
2. Open Vouchers and choose the billing month, year, academic session and class. Optionally select one student. Generate creates up to 500 active students' vouchers in one transaction. Existing active vouchers for the same student/month remain unchanged.
3. Open a voucher or use Collection to record a full or partial payment. Each payment creates a numbered receipt. Print / Save PDF uses the browser print dialog.
4. Defaulters lists overdue unpaid balances. Reports provides date/class filters, collection totals, balances and CSV exports. Student profiles show the actual voucher history.

Fees, discounts, student/class details and fine rules are saved on each voucher. Later setup changes do not modify issued vouchers. Yearly fees are charged once per calendar year and one-time fees once per student, based on uncancelled voucher history. Previous balances remain on their original vouchers rather than being billed again.

Fine calculations include grace days and an optional positive cap. A fully paid voucher stops accruing fines on its settlement date. Past payment dates cannot precede the billing month or the last valid payment. Reports include both endpoints of their payment-date range; outstanding balances represent today's balances.

Duplicate payment submissions reuse their original receipt. Incorrect payments require reversal with a reason; the original receipt remains visible and is excluded from collected totals. Reverse all payments before cancelling a voucher. Cancellation retains its history and permits a replacement voucher for that month. All ledger mutations record the operator and an activity entry within the same transaction.

Validation: FeeBillingTest covers snapshots, duplicate generation/submissions, discounts, full/partial payments, fines, reversals, cancellations, authorization, transaction rollback, exports and student history. FeeSetupTest and FeeCalculatorServiceTest verify setup/calculation behavior; DashboardTest verifies actual ledger totals. Local browser checks cover six billing pages at desktop and mobile widths without saving preview transactions.

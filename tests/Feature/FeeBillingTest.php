<?php
namespace Tests\Feature;

use App\Models\{FeeVoucher, FeePayment, FeeSetting, FeeStructure, Student, User, Activity};
use App\Services\{FeeBillingService, FeeCalculatorService, DashboardStatsService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesFeeFixtures;
use Tests\TestCase;

class FeeBillingTest extends TestCase
{
    use RefreshDatabase, CreatesFeeFixtures;
    private User $admin;

    public function test_student_fee_history_shows_paid_partial_and_unpaid_billing_months(): void
    {
        $student = $this->feeStudent();
        $this->feeType($student);
        $service = app(FeeBillingService::class);
        $august = $service->generate($student,8,2026,$this->admin);
        $september = $service->generate($student,9,2026,$this->admin);
        $service->generate($student,10,2026,$this->admin);
        $paid = $service->collect($august,$this->payment(['amount'=>1000]),$this->admin);
        $service->collect($september,$this->payment(),$this->admin);
        $this->get(route('fees.students.history',$student))->assertOk()->assertSee('August 2026')->assertSee('September 2026')->assertSee('October 2026')
            ->assertSee('Payment History')->assertViewHas('monthCounts',fn($counts) => $counts->get('Paid') === 1 && $counts->get('Partial') === 1 && $counts->get('Unpaid') === 1)
            ->assertViewHas('months',fn($months) => $months->count() === 3 && $months->sum('paid') === 140000 && $months->sum('remaining') === 160000);
        $this->get(route('fees.reports.index'))->assertOk()->assertSee(route('fees.students.history',$student),false);
        $this->post(route('fees.payments.reverse',$paid),['reason'=>'Incorrect receipt'])->assertSessionHasNoErrors();
        $this->get(route('fees.students.history',$student))->assertOk()->assertViewHas('months',fn($months) => $months->sum('paid') === 40000 && $months->sum('remaining') === 260000);
    }

    public function test_fee_history_handles_students_without_vouchers_and_excludes_cancelled_months(): void
    {
        $student = $this->feeStudent();
        $this->get(route('fees.students.history',$student))->assertOk()->assertViewHas('months',fn($months) => $months->isEmpty());
        $this->feeType($student);
        $voucher = app(FeeBillingService::class)->generate($student,10,2026,$this->admin);
        $voucher->update(['cancelled_at'=>now()]);
        $this->get(route('fees.students.history',$student))->assertOk()->assertViewHas('months',fn($months) => $months->isEmpty());
        $this->actingAs(User::factory()->create(['role'=>'Teacher']));
        $this->get(route('fees.students.history',$student))->assertForbidden();
    }

    public function test_student_balance_report_tracks_unpaid_partial_paid_and_reversed_receipts(): void
    {
        $voucher = $this->voucher();
        $this->get(route('fees.reports.index'))->assertOk()->assertViewHas('studentFees', fn($rows) => $rows->first()['status'] === 'Unpaid' && $rows->first()['remaining'] === 100000);
        $this->post(route('fees.payments.store',$voucher),$this->payment())->assertSessionHasNoErrors();
        $payment = FeePayment::firstOrFail();
        $this->get(route('fees.reports.index'))->assertOk()->assertViewHas('studentFees', fn($rows) => $rows->first()['status'] === 'Partial' && $rows->first()['paid'] === 40000 && $rows->first()['remaining'] === 60000);
        $this->post(route('fees.payments.store',$voucher),$this->payment(['amount'=>600]))->assertSessionHasNoErrors();
        $this->get(route('fees.reports.index'))->assertOk()->assertViewHas('studentFees', fn($rows) => $rows->first()['status'] === 'Paid' && $rows->first()['remaining'] === 0);
        $this->post(route('fees.payments.reverse',$payment),['reason'=>'Incorrect receipt'])->assertSessionHasNoErrors();
        $this->get(route('fees.reports.index'))->assertOk()->assertViewHas('studentFees', fn($rows) => $rows->first()['paid'] === 60000 && $rows->first()['remaining'] === 40000 && $rows->first()['status'] === 'Partial');
    }

    public function test_student_balance_filters_and_export_keep_balances_independent_of_collection_dates(): void
    {
        $voucher = $this->voucher();
        $this->get(route('fees.reports.index',['from'=>'2026-09-01','to'=>'2026-09-30','student_search'=>$voucher->admission_no,'fee_status'=>'Unpaid']))
            ->assertOk()->assertViewHas('studentFees',fn($rows) => $rows->total() === 1 && $rows->first()['remaining'] === 100000);
        $this->get(route('fees.reports.index',['fee_status'=>'Paid']))->assertOk()->assertViewHas('studentFees',fn($rows) => $rows->total() === 0);
        $this->get(route('fees.reports.index',['student_search'=>'no-such-student']))->assertOk()->assertViewHas('studentFees',fn($rows) => $rows->total() === 0);
        $this->get(route('fees.reports.index',['export'=>'students']))->assertOk()->assertDownload('student-fee-balances.csv');
        $voucher->update(['cancelled_at'=>now()]);
        $this->get(route('fees.reports.index'))->assertOk()->assertViewHas('studentFees',fn($rows) => $rows->total() === 0);
    }
    protected function setUp(): void
    {
        parent::setUp(); $this->travelTo(\Carbon\Carbon::parse('2026-10-07 12:00:00', 'Asia/Karachi'));
        $this->admin = User::factory()->create(['role' => 'Admin']); $this->actingAs($this->admin);
    }
    private function voucher(): FeeVoucher
    {
        $student = $this->feeStudent(); $this->feeType($student);
        return app(FeeBillingService::class)->generate($student, 10, 2026, $this->admin);
    }
    private function payment(array $extra = []): array
    {
        return array_replace(['amount' => '400.00', 'payment_date' => '2026-10-07', 'method' => 'Cash', 'submission_token' => (string) Str::uuid()], $extra);
    }
    public function test_monthly_vouchers_are_unique_snapshot_discounts_and_never_duplicate_arrears(): void
    {
        $student = $this->feeStudent(); $type = $this->feeType($student);
        $this->feeDiscount($student, 'percent', 10);
        $data = ['academic_session_id' => $student->academic_session_id, 'month' => 10, 'year' => 2026];
        $this->post(route('fees.vouchers.store'), $data)->assertSessionHasNoErrors();
        $voucher = FeeVoucher::firstOrFail(); $this->assertSame(90000, $voucher->total_cents);
        $this->post(route('fees.vouchers.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('fee_vouchers', 1);
        FeeStructure::where('fee_type_id', $type->id)->update(['amount' => 2000]);
        $this->assertSame(90000, $voucher->fresh()->total_cents);
        $next = app(FeeBillingService::class)->generate($student, 11, 2026, $this->admin);
        $this->assertSame(180000, $next->total_cents);
        $this->get(route('fees.vouchers.show', $voucher))->assertOk()->assertSee('1,800.00');
        $this->get(route('fees.vouchers.index'))->assertOk()->assertSee($voucher->number);
    }
    public function test_partial_full_payments_receipts_duplicate_submissions_reversals_and_totals(): void
    {
        $voucher = $this->voucher(); $data = $this->payment();
        $this->post(route('fees.payments.store', $voucher), $data)->assertSessionHasNoErrors()->assertRedirect();
        $payment = FeePayment::firstOrFail(); $this->assertSame(60000, $voucher->fresh()->balance_cents);
        $this->post(route('fees.payments.store', $voucher), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('fee_payments', 1);
        $this->post(route('fees.payments.store', $voucher), array_replace($data, ['amount' => 500]))->assertSessionHasErrors('amount');
        $this->get(route('fees.receipts.show', $payment))->assertOk()->assertSee($payment->number)->assertSee('400.00');
        $this->post(route('fees.payments.store', $voucher), $this->payment(['amount' => 601]))->assertSessionHasErrors('amount');
        $this->post(route('fees.payments.store', $voucher), $this->payment(['amount' => 600]))->assertSessionHasNoErrors();
        $this->assertSame('Paid', $voucher->fresh()->display_status);
        $this->get(route('fees.collection.index'))->assertOk()->assertViewHas('vouchers', fn ($v) => $v->isEmpty());
        $this->post(route('fees.payments.reverse', $payment), ['reason' => 'Incorrect receipt'])->assertSessionHasNoErrors();
        $this->assertSame(40000, $voucher->fresh()->balance_cents);
        $this->assertDatabaseCount('fee_payments', 2);
        $this->post(route('fees.payments.reverse', $payment), ['reason' => 'Duplicate reversal'])->assertSessionHasErrors('reason');
        $this->get(route('fees.reports.index'))->assertOk()->assertViewHas('collected', 60000)->assertViewHas('outstanding', 40000);
        $stats = app(DashboardStatsService::class)->fees('2026-10');
        $this->assertEquals(600, $stats['collected']); $this->assertEquals(400, $stats['pending']);
        $this->get(route('fees.reports.index', ['export' => 'csv']))->assertOk()->assertDownload('fee-collection.csv');
    }
    public function test_fines_use_snapshot_grace_days_caps_and_stop_after_full_payment(): void
    {
        FeeSetting::findOrFail(1)->update(['due_day' => 1, 'fine_type' => 'per_day', 'fine_amount' => 10, 'grace_days' => 2, 'fine_max_cap' => 25]);
        $voucher = $this->voucher();
        $service = app(FeeBillingService::class);
        $this->assertSame(0, $service->fine($voucher, '2026-10-03'));
        $this->assertSame(1000, $service->fine($voucher, '2026-10-04'));
        $this->assertSame(2500, $service->fine($voucher));
        FeeSetting::find(1)->update(['fine_amount' => 500]);
        $this->assertSame(2500, $service->fine($voucher));
        $this->get(route('fees.defaulters.index'))->assertOk()->assertSee($voucher->number)->assertViewHas('outstanding', 102500);
        $this->post(route('fees.payments.store', $voucher), $this->payment(['amount' => 1025]))->assertSessionHasNoErrors();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-20 12:00:00'));
        $this->assertSame(0, $voucher->fresh()->balance_cents);
        $this->assertSame(2500, $service->fine($voucher->fresh()));
        $this->get(route('fees.defaulters.index'))->assertViewHas('vouchers', fn ($v) => $v->isEmpty());
    }
    public function test_billing_frequency_uses_history_cancelled_vouchers_can_be_replaced(): void
    {
        $student = $this->feeStudent(); $this->feeType($student, 'Monthly', 'monthly', 100);
        $this->feeType($student, 'Admission', 'one_time', 50); $this->feeType($student, 'Annual', 'yearly', 200);
        $service = app(FeeBillingService::class);
        $first = $service->generate($student, 10, 2026, $this->admin); $this->assertSame(35000, $first->total_cents);
        $second = $service->generate($student, 11, 2026, $this->admin); $this->assertSame(10000, $second->total_cents);
        $service->cancel($first, 'Reissue requested', $this->admin);
        $replacement = $service->generate($student, 10, 2026, $this->admin);
        $this->assertNotSame($first->id, $replacement->id); $this->assertSame(35000, $replacement->total_cents);
        $nextYear = $service->generate($student, 1, 2027, $this->admin); $this->assertSame(30000, $nextYear->total_cents);
        $this->post(route('fees.payments.store', $first), $this->payment())->assertSessionHasErrors('amount');
    }
    public function test_cancellation_requires_reversing_payments_and_preserves_history(): void
    {
        $voucher = $this->voucher();
        $this->post(route('fees.payments.store', $voucher), $this->payment())->assertSessionHasNoErrors();
        $this->post(route('fees.vouchers.cancel', $voucher), ['reason' => 'Wrong invoice'])->assertSessionHasErrors('reason');
        $this->post(route('fees.payments.reverse', FeePayment::first()), ['reason' => 'Wrong invoice'])->assertSessionHasNoErrors();
        $this->post(route('fees.vouchers.cancel', $voucher), ['reason' => 'Wrong invoice'])->assertSessionHasNoErrors();
        $this->assertSame('Cancelled', $voucher->fresh()->display_status);
        $this->assertSame(0, $voucher->fresh()->balance_cents);
        $this->get(route('fees.vouchers.show', $voucher))->assertOk()->assertSee('Wrong invoice');
        $this->assertDatabaseCount('fee_voucher_items', 1);
    }
    public function test_payments_validate_dates_amounts_and_authorization(): void
    {
        $voucher = $this->voucher();
        foreach ([['amount' => 0], ['amount' => -10], ['amount' => '0.001'], ['method' => 'Unknown'], ['payment_date' => '2026-10-08'], ['payment_date' => '2026-09-30']] as $invalid) {
            $this->post(route('fees.payments.store', $voucher), $this->payment($invalid))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('fee_payments', 0);
        $this->actingAs(User::factory()->create(['role' => 'Teacher']));
        $this->get(route('fees.vouchers.show', $voucher))->assertForbidden();
        $this->post(route('fees.payments.store', $voucher), $this->payment())->assertForbidden();
        $this->post(route('fees.vouchers.store'), [])->assertForbidden();
        auth()->logout(); $this->get(route('fees.reports.index'))->assertRedirect(route('login'));
    }
    public function test_bulk_generation_and_payments_roll_back_when_audit_fails(): void
    {
        $voucher = $this->voucher();
        Activity::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            app(FeeBillingService::class)->collect($voucher, $this->payment(), $this->admin);
            $this->fail('Payment should roll back when audit fails.');
        } catch (\RuntimeException $error) { $this->assertSame('Audit unavailable', $error->getMessage()); }
        finally { Activity::flushEventListeners(); }
        $this->assertDatabaseCount('fee_payments', 0); $this->assertSame(100000, $voucher->fresh()->balance_cents);
        $other = $this->feeStudent(['class_id' => \App\Models\SchoolClass::create(['name' => 'No Fee Class'])->id]);
        $this->post(route('fees.vouchers.store'), ['academic_session_id' => $other->academic_session_id, 'month' => 11, 'year' => 2026])->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('fee_vouchers', 1);
    }
    public function test_student_profile_shows_real_ledger_and_not_sample_payments(): void
    {
        $voucher = $this->voucher();
        $this->get(route('students.show', $voucher->student))->assertOk()->assertSee($voucher->number)->assertDontSee('Annual Maintenance');
        $this->get(route('fees.defaulters.index', ['export' => 'csv']))->assertOk()->assertDownload('defaulters.csv');
    }
}

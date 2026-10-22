<?php

namespace Tests\Feature;

use App\Models\{AccountExpense, AccountIncome, FeePayment, User};
use App\Services\FeeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesFeeFixtures;
use Tests\TestCase;

class AccountsTest extends TestCase
{
    use RefreshDatabase, CreatesFeeFixtures;

    public function test_income_expenses_and_private_attachments(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin);
        $this->post(route('accounts.income.store'), [
            'title' => 'Donation', 'category' => 'Donation', 'amount' => '50000.25',
            'income_date' => today()->toDateString(), 'payment_method' => 'Cash',
            'received_from' => 'School supporter', 'attachment' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('accounts.expenses.store'), [
            'title' => 'Electricity bill', 'category' => 'Electricity', 'amount' => '15000.10',
            'expense_date' => today()->toDateString(), 'payment_method' => 'Bank Transfer',
            'paid_to' => 'Power company', 'description' => 'Monthly bill',
        ])->assertSessionHasNoErrors();
        $this->assertSame(5000025, AccountIncome::first()->amount_cents);
        $this->assertSame(1500010, AccountExpense::first()->amount_cents);
        $this->get(route('accounts.dashboard'))->assertOk()->assertViewHas('totals', fn ($t) => (int) $t['balance'] === 3500015);
        foreach (['income.index', 'expenses.index', 'salaries.index', 'transactions.index', 'reports.index'] as $route) $this->get(route('accounts.'.$route))->assertOk();
        $this->get(route('accounts.attachments.show', ['income', AccountIncome::first()->id]))->assertOk();
        $this->get(route('accounts.reports.index', ['export' => 'csv']))->assertDownload('accounts-report.csv');
        $this->actingAs(User::factory()->create(['role' => 'Teacher']));
        $this->get(route('accounts.dashboard'))->assertForbidden();
        $this->get(route('accounts.attachments.show', ['income', AccountIncome::first()->id]))->assertForbidden();
    }

    public function test_paid_salary_counts_once_and_pending_salary_does_not_reduce_balance(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
        $teacher = \App\Models\Teacher::create(['name' => 'Ali', 'employee_id' => 'TEST-1', 'cnic' => '12345-1234567-1', 'phone' => '03001234567', 'gender' => 'Male', 'department_id' => \App\Models\Department::create(['name' => 'Science'])->id, 'designation_id' => \App\Models\Designation::create(['name' => 'Teacher'])->id, 'joining_date' => '2026-01-01', 'status' => 'Active']);
        $payroll = $teacher->payrolls()->create(['month' => today()->startOfMonth()->toDateString(), 'basic_salary' => 40000, 'allowances' => 5000, 'deductions' => 2000, 'net_salary' => 43000]);
        $this->get(route('accounts.dashboard'))->assertViewHas('totals', fn ($t) => (int) $t['salaries'] === 0);
        $this->get(route('accounts.salaries.index'))->assertOk()->assertSee('Ali');
        $data = ['payment_date' => today()->toDateString(), 'payment_method' => 'Bank Transfer'];
        $this->post(route('accounts.salaries.paid', $payroll), $data)->assertSessionHasNoErrors();
        $this->post(route('accounts.salaries.paid', $payroll), $data)->assertSessionHasNoErrors();
        $this->get(route('accounts.dashboard'))->assertViewHas('totals', fn ($t) => (int) $t['salaries'] === 4300000 && (int) $t['balance'] === -4300000);
        $this->get(route('accounts.transactions.index', ['type' => 'Salary', 'from' => today()->toDateString(), 'to' => today()->toDateString()]))->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
    }

    public function test_fee_receipts_automatically_enter_ledger_and_reversals_remove_them(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin);
        $student = $this->feeStudent();
        $this->feeType($student);
        $service = app(FeeBillingService::class);
        $voucher = $service->generate($student, (int) now()->format('m'), (int) now()->format('Y'), $admin);
        $payment = $service->collect($voucher, ['amount' => 400, 'payment_date' => today()->toDateString(), 'method' => 'Cash', 'submission_token' => (string) Str::uuid()], $admin);
        $this->get(route('accounts.dashboard'))->assertOk()->assertViewHas('totals', fn ($t) => (int) $t['income'] === 40000);
        $this->get(route('accounts.transactions.index'))->assertSee($payment->number);
        $this->post(route('fees.payments.reverse', $payment), ['reason' => 'Incorrect receipt'])->assertSessionHasNoErrors();
        $this->get(route('accounts.dashboard'))->assertViewHas('totals', fn ($t) => (int) $t['balance'] === 0);
    }
}

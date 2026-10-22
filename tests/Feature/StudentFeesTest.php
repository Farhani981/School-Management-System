<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\FeePayment;
use App\Models\FeeSetting;
use App\Models\FeeVoucher;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\FeeBillingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesFeeFixtures;
use Tests\TestCase;

class StudentFeesTest extends TestCase
{
    use RefreshDatabase, CreatesFeeFixtures;

    private User $admin;
    private User $studentUser;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Parent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->admin->assignRole('Admin');

        FeeSetting::firstOrCreate(['id' => 1], [
            'due_day' => 15,
            'fine_type' => 'fixed',
            'fine_amount' => 100,
            'grace_days' => 2,
            'fine_max_cap' => 500,
            'voucher_prefix' => 'FV',
            'receipt_prefix' => 'FR',
        ]);

        $this->student = $this->feeStudent();
        $this->studentUser = User::factory()->create([
            'email' => 'student.portal.fees@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Student',
        ]);
        $this->studentUser->assignRole('Student');
        $this->student->update(['user_id' => $this->studentUser->id]);
    }

    private function generateVoucher(Student $student, int $month, int $year): FeeVoucher
    {
        $type = \App\Models\FeeType::firstOrCreate(
            ['name' => 'Tuition'],
            ['frequency' => 'monthly', 'is_active' => true]
        );
        \App\Models\FeeStructure::firstOrCreate([
            'academic_session_id' => $student->academic_session_id,
            'school_class_id' => $student->class_id,
            'fee_type_id' => $type->id,
        ], ['amount' => 1000]);

        return app(FeeBillingService::class)->generate($student, $month, $year, $this->admin);
    }

    public function test_fee_summary_returns_safe_defaults_when_no_data(): void
    {
        $response = $this->actingAs($this->studentUser)
            ->getJson('/student/fees/summary');

        $response->assertOk()
            ->assertJson([
                'totalPending' => 0.0,
                'totalPaidThisSession' => 0.0,
                'overdueAmount' => 0.0,
                'nextDueDate' => null,
                'status' => 'paid',
                'latest' => null,
            ]);
    }

    public function test_fee_summary_for_pending_unpaid_and_overdue_students(): void
    {
        // 1. Pending voucher due in future (current month 10/2026, due in 5 days)
        $futureVoucher = $this->generateVoucher($this->student, 10, 2026);
        $futureVoucher->update(['due_date' => today()->addDays(5)]);

        $response = $this->actingAs($this->studentUser)
            ->getJson('/student/fees/summary');

        $response->assertOk()
            ->assertJson([
                'status' => 'pending',
                'totalPending' => 1000.0,
                'overdueAmount' => 0.0,
            ]);
        $this->assertNotNull($response->json('latest'));
        $this->assertSame($futureVoucher->number, $response->json('latest.challan_no'));

        // 2. Overdue voucher due in the past (month 9/2026)
        $overdueVoucher = $this->generateVoucher($this->student, 9, 2026);
        $overdueVoucher->update(['due_date' => today()->subDays(10)]);

        $responseOverdue = $this->actingAs($this->studentUser)
            ->getJson('/student/fees/summary');

        $responseOverdue->assertOk();
        $this->assertSame('overdue', $responseOverdue->json('status'));
        $this->assertGreaterThan(0, $responseOverdue->json('overdueAmount'));

        // 3. Settling both vouchers marks status as paid
        $service = app(FeeBillingService::class);
        $service->collect($futureVoucher, [
            'amount' => '1000.00',
            'payment_date' => today()->toDateString(),
            'method' => 'Cash',
            'submission_token' => (string) Str::uuid(),
        ], $this->admin);

        $balanceOverdue = $service->balance($overdueVoucher);
        $service->collect($overdueVoucher, [
            'amount' => number_format($balanceOverdue / 100, 2, '.', ''),
            'payment_date' => today()->toDateString(),
            'method' => 'Cash',
            'submission_token' => (string) Str::uuid(),
        ], $this->admin);

        $responsePaid = $this->actingAs($this->studentUser)
            ->getJson('/student/fees/summary');

        $responsePaid->assertOk()
            ->assertJson([
                'status' => 'paid',
                'totalPending' => 0.0,
                'overdueAmount' => 0.0,
                'latest' => null,
            ]);
    }

    public function test_fee_challans_list_api_with_status_filtering(): void
    {
        $voucher1 = $this->generateVoucher($this->student, 8, 2026);
        $voucher1->update(['due_date' => today()->subDays(10)]); // overdue

        $voucher2 = $this->generateVoucher($this->student, 9, 2026);
        $voucher2->update(['due_date' => today()->addDays(10)]); // unpaid pending

        // Pay voucher 2
        app(FeeBillingService::class)->collect($voucher2, [
            'amount' => '1000.00',
            'payment_date' => today()->toDateString(),
            'method' => 'Cash',
            'submission_token' => (string) Str::uuid(),
        ], $this->admin);

        // All challans
        $resAll = $this->actingAs($this->studentUser)
            ->getJson('/student/fees/challans?status=all');

        $resAll->assertOk()->assertJsonCount(2);
        $item = $resAll->json(0);
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('challan_no', $item);
        $this->assertArrayHasKey('month', $item);
        $this->assertArrayHasKey('fee_heads', $item);
        $this->assertArrayHasKey('discount', $item);
        $this->assertArrayHasKey('late_fine', $item);
        $this->assertArrayHasKey('arrears', $item);
        $this->assertArrayHasKey('total', $item);
        $this->assertArrayHasKey('paid_amount', $item);
        $this->assertArrayHasKey('balance', $item);
        $this->assertArrayHasKey('due_date', $item);
        $this->assertArrayHasKey('status', $item);

        // Paid only
        $resPaid = $this->actingAs($this->studentUser)
            ->getJson('/student/fees/challans?status=paid');
        $resPaid->assertOk()->assertJsonCount(1);
        $this->assertSame($voucher2->id, $resPaid->json('0.id'));

        // Overdue only
        $resOverdue = $this->actingAs($this->studentUser)
            ->getJson('/student/fees/challans?status=overdue');
        $resOverdue->assertOk()->assertJsonCount(1);
        $this->assertSame($voucher1->id, $resOverdue->json('0.id'));
    }

    public function test_fee_challan_detail_api_verifies_ownership_and_returns_404_for_others(): void
    {
        $voucher = $this->generateVoucher($this->student, 10, 2026);

        // Own voucher
        $response = $this->actingAs($this->studentUser)
            ->getJson('/student/fees/challans/'.$voucher->id);

        $response->assertOk()
            ->assertJson([
                'id' => $voucher->id,
                'challan_no' => $voucher->number,
                'admission_no' => $this->student->admission_no,
            ]);

        // Another student's voucher
        $otherStudent = $this->feeStudent();
        $otherVoucher = $this->generateVoucher($otherStudent, 10, 2026);

        $responseForbidden = $this->actingAs($this->studentUser)
            ->getJson('/student/fees/challans/'.$otherVoucher->id);

        // Must return 404 (not 403, so existence is not revealed)
        $responseForbidden->assertStatus(404);
    }

    public function test_fee_challan_pdf_stream_and_ownership_check(): void
    {
        $voucher = $this->generateVoucher($this->student, 10, 2026);

        // Download own challan PDF
        $response = $this->actingAs($this->studentUser)
            ->get('/student/fees/challans/'.$voucher->id.'/pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));

        // Accessing other student's challan PDF returns 404
        $otherStudent = $this->feeStudent();
        $otherVoucher = $this->generateVoucher($otherStudent, 10, 2026);

        $responseOther = $this->actingAs($this->studentUser)
            ->get('/student/fees/challans/'.$otherVoucher->id.'/pdf');

        $responseOther->assertStatus(404);
    }

    public function test_fee_payments_list_and_receipt_pdf_download(): void
    {
        $voucher = $this->generateVoucher($this->student, 10, 2026);
        $payment = app(FeeBillingService::class)->collect($voucher, [
            'amount' => '500.00',
            'payment_date' => today()->toDateString(),
            'method' => 'Bank Transfer',
            'reference' => 'TXN-998877',
            'notes' => 'Half payment deposited',
            'submission_token' => (string) Str::uuid(),
        ], $this->admin);

        // List payments
        $resList = $this->actingAs($this->studentUser)
            ->getJson('/student/fees/payments');

        $resList->assertOk()->assertJsonCount(1);
        $this->assertSame($payment->number, $resList->json('0.receipt_no'));
        $this->assertEquals(500, $resList->json('0.amount'));
        $this->assertSame('Bank Transfer', $resList->json('0.method'));

        // Receipt PDF stream
        $resPdf = $this->actingAs($this->studentUser)
            ->get('/student/fees/payments/'.$payment->id.'/receipt');

        $resPdf->assertOk();
        $this->assertStringContainsString('application/pdf', $resPdf->headers->get('Content-Type'));

        // Another student's payment receipt returns 404
        $otherStudent = $this->feeStudent();
        $otherVoucher = $this->generateVoucher($otherStudent, 10, 2026);
        $otherPayment = app(FeeBillingService::class)->collect($otherVoucher, [
            'amount' => '500.00',
            'payment_date' => today()->toDateString(),
            'method' => 'Cash',
            'submission_token' => (string) Str::uuid(),
        ], $this->admin);

        $resOtherReceipt = $this->actingAs($this->studentUser)
            ->get('/student/fees/payments/'.$otherPayment->id.'/receipt');

        $resOtherReceipt->assertStatus(404);
    }

    public function test_guard_ignores_spoofed_student_id_in_query_or_body(): void
    {
        $otherStudent = $this->feeStudent();
        $this->generateVoucher($otherStudent, 10, 2026);

        // Query with spoofed student_id
        $response = $this->actingAs($this->studentUser)
            ->getJson('/student/fees/summary?student_id='.$otherStudent->id);

        $response->assertOk();
        // The resolved student has 0 vouchers, so totalPending must be 0, NOT the other student's amount
        $this->assertEquals(0, $response->json('totalPending'));
    }

    public function test_parent_can_view_fees_and_child_switcher_updates_data(): void
    {
        $parentUser = User::factory()->create([
            'email' => 'parent.portal.fees@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Parent',
        ]);
        $parentUser->assignRole('Parent');

        $child1 = $this->student;
        $child2 = $this->feeStudent();

        $parentUser->children()->attach([$child1->id, $child2->id]);

        $voucher1 = $this->generateVoucher($child1, 10, 2026);
        $voucher2 = $this->generateVoucher($child2, 10, 2026);

        // Acting as parent without explicit session defaults to child1
        $response1 = $this->actingAs($parentUser)
            ->getJson('/student/fees/challans');
        $response1->assertOk();
        $this->assertSame($voucher1->number, $response1->json('0.challan_no'));

        // Switch to child 2 in session
        $response2 = $this->actingAs($parentUser)
            ->withSession(['active_student_id' => $child2->id])
            ->getJson('/student/fees/challans');
        $response2->assertOk();
        $this->assertSame($voucher2->number, $response2->json('0.challan_no'));
    }

    public function test_portal_fees_page_ui_renders_successfully(): void
    {
        $this->generateVoucher($this->student, 10, 2026);

        $response = $this->actingAs($this->studentUser)
            ->get('/portal/fees');

        $response->assertOk()
            ->assertSee('Fees &amp; Billing', false)
            ->assertSee('Total Pending')
            ->assertSee('Paid This Session')
            ->assertSee('Overdue Amount')
            ->assertSee('Payment History')
            ->assertSee('Payment Instructions');
    }

    public function test_dashboard_renders_with_real_fee_data_and_challan_download_link(): void
    {
        $voucher = $this->generateVoucher($this->student, 10, 2026);

        $response = $this->actingAs($this->studentUser)
            ->get('/portal');

        $response->assertOk()
            ->assertSee('Current Fee Challan')
            ->assertSee($voucher->number)
            ->assertSee(route('student.fees.challans.pdf', $voucher->id));
    }
}

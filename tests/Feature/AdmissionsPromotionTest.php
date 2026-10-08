<?php

namespace Tests\Feature;

use App\Models\AdmissionApplication;
use App\Models\Promotion;
use App\Models\Student;
use App\Services\StudentRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdmissionsPromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(\App\Models\User::factory()->create(['role' => 'Admin']));
        foreach (['school_classes' => ['Class 1', 'Class 2', 'Class 10'], 'school_sections' => ['A', 'B'], 'academic_sessions' => ['2026-2027', '2027-2028']] as $table => $names) foreach ($names as $name) DB::table($table)->insert(['name' => $name]);
    }

    private function details(array $overrides = []): array
    {
        return array_merge(['first_name' => 'Ali', 'last_name' => 'Hassan', 'father_name' => 'Ahmed Hassan', 'date_of_birth' => '2015-01-10', 'gender' => 'Male', 'phone' => '03001234567', 'address' => 'School Road', 'admission_date' => '2026-09-01', 'class_id' => 1, 'section_id' => 1, 'academic_session_id' => 1, 'roll_number' => '1', 'status' => 'Active'], $overrides);
    }
    private function student(array $overrides = []): Student { return app(StudentRecords::class)->save(new Student, $this->details($overrides)); }
    private function application(): AdmissionApplication { return AdmissionApplication::create(['applicant_name' => 'Ali Hassan', 'father_name' => 'Ahmed Hassan', 'phone' => '03001234567', 'applying_for_class_id' => 1, 'previous_school' => 'Previous School']); }

    public function test_sidebar_has_exactly_three_children_and_active_workflows(): void
    {
        foreach (['students.index' => 'All Students', 'admissions.index' => 'Admissions', 'admissions.create' => 'Admissions', 'admissions.register' => 'Admissions', 'promotion.index' => 'Promotion', 'promotion.history' => 'Promotion'] as $route => $label) {
            $response = $this->get(route($route))->assertOk()->assertSee('aria-expanded="true"', false);
            $dom = new \DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            $children = $xpath->query('//*[@id="students-submenu"]//a');
            $this->assertCount(3, $children);
            $active = $xpath->query('//*[@id="students-submenu"]//a[@aria-current="page"]');
            $this->assertCount(1, $active);
            $this->assertStringContainsString($label, $active->item(0)->textContent);
        }
    }

    public function test_applications_validate_and_approval_creates_one_linked_student(): void
    {
        $this->post(route('admissions.applications.store'), [])->assertSessionHasErrors(['applicant_name', 'father_name', 'phone', 'applying_for_class_id']);
        $this->post(route('admissions.applications.store'), ['applicant_name' => 'Ali Hassan', 'father_name' => 'Ahmed Hassan', 'phone' => '03001234567', 'applying_for_class_id' => 1])->assertSessionHasNoErrors();
        $application = AdmissionApplication::firstOrFail();
        $this->get(route('admissions.applications.review', $application))->assertOk()->assertSee('Approve')->assertSee('Ali');
        $this->post(route('admissions.applications.approve', $application), [])->assertSessionHasErrors('date_of_birth');
        $this->assertSame('pending', $application->fresh()->status);
        $this->assertSame(0, Student::count());
        $this->post(route('admissions.applications.approve', $application), $this->details(['admission_no' => 'FORGED']))->assertSessionHasNoErrors();
        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertNotNull($application->reviewed_at);
        $student = $application->student;
        $this->assertSame('ADM-2026-0001', $student->admission_no);
        $this->post(route('admissions.applications.approve', $application), $this->details(['roll_number' => '2']))->assertSessionHasErrors('application');
        $this->assertSame(1, Student::count());
        $this->get(route('admissions.applications.review', $application))->assertStatus(409);
        $this->post(route('admissions.applications.reject', $application))->assertSessionHasErrors('application');
    }

    public function test_rejection_is_final_and_register_exports_respect_date_range(): void
    {
        $application = $this->application();
        $this->post(route('admissions.applications.reject', $application))->assertSessionHasNoErrors();
        $this->assertSame('rejected', $application->fresh()->status);
        $this->assertSame(0, Student::count());
        $this->post(route('admissions.applications.approve', $application), $this->details())->assertSessionHasErrors('application');
        $first = $this->student();
        $this->student(['admission_date' => '2026-08-01', 'roll_number' => '2']);
        $first->delete();
        $filters = ['from' => '01-09-2026', 'to' => '30-09-2026'];
        $response = $this->get(route('admissions.register', $filters))->assertOk()->assertSee('archived');
        $this->assertSame(1, $response['students']->total());
        foreach (['xlsx', 'pdf'] as $format) $this->get(route('admissions.register.export', $filters + ['format' => $format]))->assertOk()->assertDownload('admission-register.'.$format);
        $this->get(route('admissions.register', ['from' => '30-09-2026', 'to' => '01-09-2026']))->assertSessionHasErrors('to');
    }

    public function test_active_default_all_status_filter_family_and_certificates(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $active = $this->student();
        \Illuminate\Support\Facades\Storage::disk('public')->put('portrait.jpg', \Illuminate\Http\UploadedFile::fake()->image('portrait.jpg', 320, 240)->getContent());
        $active->update(['photo' => 'portrait.jpg']);
        $left = $this->student(['roll_number' => '2', 'status' => 'Left']);
        $passed = $this->student(['roll_number' => '3', 'status' => 'Passed out']);
        $response = $this->get(route('students.index'))->assertOk()->assertSee($active->admission_no)->assertDontSee($left->admission_no)->assertDontSee($passed->admission_no);
        $this->assertSame('Active', $response['filters']['status']);
        $all = $this->get(route('students.index', ['status' => '']))->assertOk();
        $this->assertSame(3, $all['students']->total());
        $this->get(route('students.index', ['status' => 'Left']))->assertOk()->assertSee($left->admission_no)->assertDontSee($active->admission_no);
        $profile = $this->get(route('students.show', $active))->assertOk()->assertSee('data-profile-tab="family"', false)->assertSee('Certificates');
        foreach (['leaving', 'character', 'bonafide'] as $type) {
            $pdf = $this->get(route('students.certificate', [$active, $type]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $pdf->getContent());
        }
        $this->get(route('students.certificate', [$active, 'invalid']))->assertNotFound();
        $this->post(route('students.actions'), ['students' => [$active->id, $left->id], 'action' => 'id_cards'])->assertOk()->assertDownload('student-id-cards.pdf');
        $this->post(route('students.actions'), ['students' => [$active->id], 'action' => 'status', 'status' => 'Passed out'])->assertSessionHasNoErrors();
        $this->assertSame('Passed out', $active->fresh()->status);
        $this->post(route('students.actions'), ['students' => [$left->id], 'action' => 'delete'])->assertSessionHasErrors('action');
    }

    public function test_mixed_promotion_actions_allocate_section_rolls_and_record_history(): void
    {
        $promote = $this->student();
        $detain = $this->student(['roll_number' => '2']);
        $left = $this->student(['roll_number' => '3']);
        $passed = $this->student(['class_id' => 3]);
        $this->student(['academic_session_id' => 2, 'class_id' => 2, 'section_id' => 2, 'roll_number' => '7']);
        $page = $this->get(route('promotion.index', ['class_id' => 3]))->assertOk();
        $this->assertStringContainsString('value="passed_out" selected', $page->getContent());
        $data = ['students' => [$promote->id, $detain->id, $left->id, $passed->id], 'decisions' => [$promote->id => 'promote', $detain->id => 'detain', $left->id => 'left', $passed->id => 'passed_out'], 'target_session_id' => 2, 'target_class_id' => 2, 'target_section_id' => 2];
        $this->post(route('promotion.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('promotion.history'));
        $this->assertSame(2, $promote->fresh()->class_id);
        $this->assertSame('8', $promote->fresh()->roll_number);
        $this->assertSame(1, $detain->fresh()->class_id);
        $this->assertSame(1, $detain->fresh()->section_id);
        $this->assertSame(2, $detain->fresh()->academic_session_id);
        $this->assertSame('1', $detain->fresh()->roll_number);
        $this->assertSame('Left', $left->fresh()->status);
        $this->assertSame('Passed out', $passed->fresh()->status);
        $this->assertDatabaseCount('promotions', 4);
        $this->assertDatabaseHas('promotions', ['student_id' => $detain->id, 'from_class_id' => 1, 'to_class_id' => 1, 'to_session_id' => 2, 'action' => 'detain']);
        $this->assertDatabaseHas('promotions', ['student_id' => $left->id, 'action' => 'left', 'to_class_id' => null]);
        $this->get(route('promotion.history'))->assertOk()->assertSee('Detain')->assertSee('Passed out');
        $passed->delete();
        $this->assertSame($passed->id, Promotion::where('student_id', $passed->id)->firstOrFail()->student->id);
    }

    public function test_promotion_rolls_back_all_decisions_and_counters_on_invalid_student(): void
    {
        $first = $this->student();
        $inactive = $this->student(['roll_number' => '2', 'status' => 'Inactive']);
        $data = ['students' => [$first->id, $inactive->id], 'decisions' => [$first->id => 'promote', $inactive->id => 'left'], 'target_session_id' => 2, 'target_class_id' => 2, 'target_section_id' => 1];
        $this->post(route('promotion.store'), $data)->assertSessionHasErrors('students');
        $this->assertSame(1, $first->fresh()->class_id);
        $this->assertDatabaseCount('promotions', 0);
        $this->assertDatabaseCount('roll_sequences', 0);
        $data['students'] = [$first->id];
        $this->post(route('promotion.store'), $data)->assertSessionHasErrors('decisions');
        $this->post(route('promotion.store'), ['students' => [$first->id], 'decisions' => [$first->id => 'promote']])->assertSessionHasErrors('target_session_id');
        $this->post(route('promotion.store'), ['students' => [$first->id], 'decisions' => [$first->id => ['malformed']]])->assertSessionHasErrors('decisions.'.$first->id);
    }
}

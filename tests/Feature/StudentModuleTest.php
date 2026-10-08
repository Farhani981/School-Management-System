<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Services\StudentRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StudentModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(\App\Models\User::factory()->create(['role' => 'Admin']));
        foreach (['school_classes' => ['Class 1', 'Class 2'], 'school_sections' => ['A', 'B'], 'academic_sessions' => ['2026-2027', '2027-2028']] as $table => $names) {
            foreach ($names as $name) DB::table($table)->insert(['name' => $name]);
        }
    }

    private function details(array $overrides = []): array
    {
        return array_merge(['first_name' => 'Ali', 'last_name' => 'Hassan', 'father_name' => 'Ahmed Hassan', 'date_of_birth' => '2015-01-10', 'gender' => 'Male', 'phone' => '03001234567', 'address' => 'School Road', 'admission_date' => '2026-09-01', 'class_id' => 1, 'section_id' => 1, 'academic_session_id' => 1, 'roll_number' => '1', 'status' => 'Active'], $overrides);
    }

    private function student(array $overrides = []): Student
    {
        return app(StudentRecords::class)->save(new Student, $this->details($overrides));
    }

    public function test_admission_counter_reserves_archived_numbers_and_restarts_per_year(): void
    {
        $first = $this->student();
        $this->assertSame('ADM-2026-0001', $first->admission_no);
        $first->delete();
        $second = $this->student(['roll_number' => '2']);
        $this->assertSame('ADM-2026-0002', $second->admission_no);
        $nextYear = $this->student(['roll_number' => '3', 'admission_date' => '2027-01-01']);
        $this->assertSame('ADM-2027-0001', $nextYear->admission_no);
        Student::create($this->details(['admission_no' => 'ADM-2026-0003', 'roll_number' => '4']));
        $this->assertSame('ADM-2026-0004', $this->student(['roll_number' => '5'])->admission_no);
    }

    public function test_dates_relationships_roll_validation_and_readonly_create(): void
    {
        $this->get(route('students.create'))->assertOk()->assertSee('readonly', false)->assertSee('dd-mm-yyyy');
        $this->post(route('students.store'), $this->details(['date_of_birth' => '10-01-2015', 'admission_date' => '01-09-2026', 'admission_no' => 'FORGED']))->assertSessionHasNoErrors();
        $student = Student::firstOrFail();
        $this->assertSame('ADM-2026-0001', $student->admission_no);
        $this->get(route('students.show', $student))->assertOk()->assertSee('10-01-2015')->assertSee('01-09-2026');
        $this->get(route('students.edit', $student))->assertOk()->assertSee('AH')->assertDontSee('Â·');
        $this->post(route('students.store'), $this->details())->assertSessionHasErrors('roll_number');
        $this->post(route('students.store'), $this->details(['class_id' => 2]))->assertSessionHasNoErrors();
        $this->post(route('students.store'), $this->details(['roll_number' => '3', 'guardian_name' => 'Test', 'guardian_phone' => '03001112222', 'guardian_relationship' => 'Cousin']))->assertSessionHasErrors('guardian_relationship');
        $this->post(route('students.store'), $this->details(['roll_number' => '3', 'date_of_birth' => '31-02-2015']))->assertSessionHasErrors('date_of_birth');
    }

    public function test_sorted_filtered_pagination_and_siblings(): void
    {
        $first = $this->student(['first_name' => 'Zain', 'family_id' => 'F1']);
        $second = $this->student(['first_name' => 'Ayesha', 'roll_number' => '2']);
        $family = $this->student(['first_name' => 'Hina', 'roll_number' => '3', 'family_id' => 'F1', 'father_name' => 'Other Father', 'phone' => '03009999999']);
        $this->assertEqualsCanonicalizing([$second->id, $family->id], $first->siblings()->pluck('id')->all());
        $this->get(route('students.show', $first))->assertOk()->assertSee('Ayesha')->assertSee('Hina')->assertSee('data-profile-tab="fees"', false);
        for ($i = 4; $i <= 12; $i++) $this->student(['first_name' => 'Student '.$i, 'roll_number' => (string)$i]);
        $response = $this->get(route('students.index', ['sort' => 'name', 'direction' => 'asc', 'per_page' => 10, 'class_id' => 1]));
        $response->assertOk();
        $this->assertSame($second->id, $response['students']->first()->id);
        $this->assertSame(10, $response['students']->perPage());
        $this->assertStringContainsString('sort=name', $response['students']->nextPageUrl());
        $this->get(route('students.index', ['sort' => 'DROP TABLE students']))->assertSessionHasErrors('sort');
        $this->get(route('students.index', ['search' => 'Zain Hassan']))->assertOk()->assertSee($first->admission_no)->assertDontSee($second->admission_no);
    }

    public function test_bulk_status_soft_delete_and_atomic_promotion(): void
    {
        $first = $this->student();
        $second = $this->student(['roll_number' => '2']);
        $ids = [$first->id, $second->id];
        $this->post(route('students.bulk'), ['students' => $ids, 'action' => 'status', 'status' => 'Left'])->assertSessionHasNoErrors();
        $this->assertSame('Left', $first->fresh()->status);
        $this->post(route('students.bulk'), ['students' => $ids, 'action' => 'status', 'status' => 'Active'])->assertSessionHasNoErrors();
        $this->student(['class_id' => 2, 'section_id' => 2, 'academic_session_id' => 2, 'roll_number' => '11']);
        $data = ['students' => $ids, 'action' => 'promote', 'class_id' => 2, 'section_id' => 2, 'academic_session_id' => 2, 'first_roll' => 10];
        $this->post(route('students.bulk'), $data)->assertSessionHasErrors('roll_number');
        $this->assertSame(1, $first->fresh()->class_id);
        $this->assertDatabaseMissing('student_histories', ['student_id' => $first->id, 'event' => 'Student promoted']);
        $this->post(route('students.bulk'), array_merge($data, ['first_roll' => 20]))->assertSessionHasNoErrors();
        $this->assertSame('20', $first->fresh()->roll_number);
        $this->assertSame('21', $second->fresh()->roll_number);
        $first->attendances()->create(['attendance_date' => '2026-10-01', 'status' => 'Present']);
        $this->post(route('students.bulk'), ['students' => $ids, 'action' => 'delete'])->assertSessionHasNoErrors();
        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
        $this->assertDatabaseHas('student_attendances', ['student_id' => $first->id]);
        $this->get(route('students.promotion'))->assertOk();
    }

    public function test_excel_and_pdf_export_use_filters_and_id_card_is_pdf(): void
    {
        Storage::fake('public');
        $first = $this->student();
        $other = $this->student(['first_name' => 'Excluded', 'roll_number' => '2', 'status' => 'Inactive']);
        $export = new \App\Exports\StudentExport(['status' => 'Active'], \App\Http\Controllers\StudentRecordsController::options());
        $this->assertSame([$first->id], $export->query()->pluck('id')->all());
        $this->get(route('students.export', ['format' => 'xlsx', 'status' => 'Active']))->assertOk()->assertDownload('students.xlsx');
        $pdf = $this->get(route('students.export', ['format' => 'pdf', 'status' => 'Active']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $card = $this->get(route('students.id-card', $first));
        $card->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $card->getContent());
        $this->get(route('students.import.template'))->assertOk()->assertDownload('students-template.xlsx');
    }

    public function test_excel_import_reports_each_invalid_row_and_preserves_valid_rows(): void
    {
        $data = $this->details();
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray(array_keys($data), null, 'A1');
        $spreadsheet->getActiveSheet()->fromArray(array_values($data), null, 'A2');
        $invalid = $this->details(['first_name' => '', 'roll_number' => '2']);
        $spreadsheet->getActiveSheet()->fromArray(array_values($invalid), null, 'A3');
        $spreadsheet->getActiveSheet()->fromArray(array_values($data), null, 'A4');
        $path = storage_path('app/testing-student-import.xlsx');
        (new Xlsx($spreadsheet))->save($path);
        try {
            $file = new UploadedFile($path, 'students.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $response = $this->post(route('students.import.store'), ['file' => $file]);
            $response->assertSessionHasNoErrors()->assertRedirect(route('students.import'));
            $this->assertSame(1, session('student_import_report.imported'));
            $this->assertSame([3, 4], array_column(session('student_import_report.errors'), 'row'));
            $this->assertSame(1, Student::count());
            $this->get(route('students.import'))->assertOk()->assertSee('rows skipped');
            $this->get(route('students.import.errors'))->assertOk()->assertDownload('student-import-errors.xlsx');
        } finally { @unlink($path); }
    }
}

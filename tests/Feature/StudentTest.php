<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_student_defaults_to_current_session_and_validates_roll_in_that_session(): void
    {
        $session = \App\Models\AcademicSession::create(['name' => '2026-2027', 'is_current' => true]);
        $this->post(route('students.store'), $this->details(['academic_session_id' => null]))->assertSessionHasNoErrors();
        $student = Student::firstOrFail();
        $this->assertEquals($session->id, $student->academic_session_id);
        $this->post(route('students.store'), $this->details(['academic_session_id' => null]))->assertSessionHasErrors('roll_number');
        $otherSession = \App\Models\AcademicSession::create(['name' => '2027-2028', 'is_current' => false]);
        $this->post(route('students.store'), $this->details(['academic_session_id' => $otherSession->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('students', ['academic_session_id' => $otherSession->id]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(\App\Models\User::factory()->create(['role' => 'Admin']));
        \Illuminate\Support\Facades\DB::table('school_classes')->insert(['id' => 6, 'name' => 'Grade 6']);
        \Illuminate\Support\Facades\DB::table('school_sections')->insert(['id' => 1, 'name' => 'A']);
    }

    public function test_guardian_documents_and_history(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('certificate.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
        $this->post(route('students.store'), $this->details([
            'guardian_name' => 'Ali', 'guardian_relationship' => 'Uncle', 'guardian_phone' => '03001112222',
            'documents' => [$file],
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $student = Student::firstOrFail();
        $this->assertSame('Ali', $student->guardian_name);
        $document = $student->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $this->get(route('students.show', $student))->assertOk()->assertSee('Admission')->assertSee('certificate.pdf');
        $this->get(route('students.documents.download', [$student, $document]))->assertDownload('certificate.pdf');
        $other = Student::create($this->details(['admission_no' => 'OTHER', 'roll_number' => '13']));
        $this->get(route('students.documents.download', [$other, $document]))->assertNotFound();
        $this->delete(route('students.documents.destroy', [$other, $document]))->assertNotFound();
        $this->post(route('students.documents.store', $student), ['documents' => [UploadedFile::fake()->create('bad.exe', 1)]])->assertSessionHasErrors('documents.0');
        $this->delete(route('students.documents.destroy', [$student, $document]))->assertRedirect();
        Storage::disk('local')->assertMissing($document->path);
        $this->assertDatabaseHas('student_histories', ['student_id' => $student->id, 'event' => 'Document deleted']);
    }

    public function test_promotion_preserves_previous_assignment_and_rejects_repeat(): void
    {
        $student = Student::create($this->details());
        \Illuminate\Support\Facades\DB::table('school_classes')->insert(['id' => 7, 'name' => 'Grade 7']);
        \Illuminate\Support\Facades\DB::table('academic_sessions')->insert(['id' => 1, 'name' => '2027-2028']);
        $data = ['class_id' => 7, 'section_id' => 1, 'academic_session_id' => 1, 'roll_number' => '15'];
        $this->post(route('students.promote', $student), $data)->assertSessionHasNoErrors();
        $this->assertEquals(7, $student->fresh()->class_id);
        $this->assertEquals(6, $student->histories()->firstOrFail()->changes['class_id']['from']);
        $this->post(route('students.promote', $student), $data)->assertSessionHasErrors('promotion');
        $this->assertEquals(1, $student->histories()->count());
        $student->update(['status' => 'Inactive']);
        $this->post(route('students.promote', $student), array_replace($data, ['class_id' => 6]))->assertSessionHasErrors('promotion');
    }

    public function test_setup_and_invalid_admission_options(): void
    {
        $this->get(route('academic-setup.index'))->assertOk();
        $this->post(route('academic-setup.store'), ['type' => 'session', 'name' => '2026-2027'])->assertSessionHasNoErrors();
        $this->post(route('academic-setup.store'), ['type' => 'session', 'name' => '2026-2027'])->assertSessionHasErrors('name');
        $this->post(route('students.store'), $this->details(['class_id' => 999]))->assertSessionHasErrors('class_id');
        $this->post(route('students.store'), $this->details(['guardian_name' => 'Ali']))->assertSessionHasErrors(['guardian_relationship', 'guardian_phone']);
    }

    public function test_inline_academic_options_can_be_saved_on_admission_and_edit(): void
    {
        $assignment = [];
        foreach (['class' => ['class_id', 'Grade 8'], 'section' => ['section_id', 'B'], 'session' => ['academic_session_id', '2028-2029']] as $type => [$field, $name]) {
            $response = $this->postJson(route('academic-setup.store'), compact('type', 'name'));
            $response->assertCreated()->assertJsonPath('name', $name);
            $assignment[$field] = $response->json('id');
            $this->postJson(route('academic-setup.store'), compact('type', 'name'))->assertUnprocessable()->assertJsonValidationErrors('name');
        }
        $this->post(route('students.store'), $this->details($assignment))->assertSessionHasNoErrors();
        $student = Student::firstOrFail();
        $this->assertDatabaseHas('students', array_merge(['id' => $student->id], $assignment));
        $this->get(route('students.edit', $student))->assertOk()->assertSee('Grade 8')->assertSee('Use Father as Guardian')->assertDontSee('name="mother_name"', false);
        $this->put(route('students.update', $student), $this->details(array_merge($assignment, ['guardian_name' => 'Ahmed Khan', 'guardian_relationship' => 'Father', 'guardian_phone' => '03001234567'])))->assertSessionHasNoErrors();
        $this->get(route('students.show', $student))->assertOk()->assertSee('Grade 8')->assertSee('2028-2029');
    }

    public function test_photo_upload_replacement_and_validation(): void
    {
        Storage::fake('public');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a7WQAAAAASUVORK5CYII=');
        $this->post(route('students.store'), $this->details(['photo' => UploadedFile::fake()->createWithContent('portrait.png', $image)]))->assertSessionHasNoErrors()->assertRedirect();
        $student = Student::firstOrFail();
        $oldPhoto = $student->photo;
        Storage::disk('public')->assertExists($oldPhoto);
        $this->put(route('students.update', $student), $this->details())->assertSessionHasNoErrors();
        $this->assertSame($oldPhoto, $student->fresh()->photo);
        $this->put(route('students.update', $student), $this->details(['photo' => UploadedFile::fake()->createWithContent('replacement.png', $image)]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($oldPhoto);
        $newPhoto = $student->fresh()->photo;
        Storage::disk('public')->assertExists($newPhoto);
        $this->put(route('students.update', $student), $this->details(['photo' => UploadedFile::fake()->create('invalid.txt', 10, 'text/plain')]))->assertSessionHasErrors('photo');
        $this->delete(route('students.destroy', $student))->assertRedirect();
        Storage::disk('public')->assertExists($newPhoto);
        $this->assertSoftDeleted($student);
    }

    private function details(array $overrides = []): array
    {
        return array_merge([
            'admission_no' => 'TEST-001', 'first_name' => 'Ayesha', 'last_name' => 'Khan',
            'father_name' => 'Ahmed Khan', 'date_of_birth' => '2012-01-10', 'gender' => 'Female',
            'phone' => '03001234567', 'address' => 'Sample address', 'admission_date' => '2026-09-01',
            'class_id' => 6, 'section_id' => 1, 'roll_number' => '12', 'status' => 'Active',
        ], $overrides);
    }

    public function test_uploaded_student_photo_is_rendered_with_same_origin_url(): void
    {
        Storage::fake('public');
        $path = 'students/photos/avatar.png';
        Storage::disk('public')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jvXkAAAAASUVORK5CYII='));
        $student = Student::create($this->details(['photo' => $path]));
        $this->get('http://127.0.0.1:8011/students')->assertOk()->assertSee('src="/storage/'.$path.'"', false)->assertSee('data-student-avatar', false);
        $this->get(route('students.show', $student))->assertOk()->assertSee('src="/storage/'.$path.'"', false);
    }

    public function test_filters_combine_with_search_and_pagination_retains_them(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            Student::create($this->details(['admission_no' => 'MATCH-'.$i, 'roll_number' => (string)$i]));
        }
        Student::create($this->details(['admission_no' => 'EXCLUDED', 'status' => 'Inactive', 'roll_number' => '20']));
        $query = ['search' => 'Khan', 'status' => 'Active', 'gender' => 'Female', 'class_id' => 6, 'section_id' => 1];
        $response = $this->get(route('students.index', $query));
        $response->assertOk()->assertSee('MATCH-16')->assertDontSee('EXCLUDED');
        $this->assertEquals(16, $response['students']->total());
        $this->assertStringContainsString('status=Active', $response['students']->nextPageUrl());
        $this->get(route('students.index', ['search' => 'nonexistent']))->assertOk()->assertSee('No students found');
    }

    public function test_student_create_view_edit_and_validation(): void
    {
        $this->get(route('students.create'))->assertOk();
        $this->post(route('students.store'), [])->assertSessionHasErrors('first_name');
        $this->post(route('students.store'), $this->details())->assertRedirect();
        $student = Student::firstOrFail();
        $this->get(route('students.show', $student))->assertOk()->assertSee('Ayesha');
        $this->get(route('students.edit', $student))->assertOk();
        $this->post(route('students.store'), $this->details(['admission_no' => $student->admission_no]))->assertSessionHasErrors('admission_no');
        $this->put(route('students.update', $student), $this->details(['first_name' => 'Sara']))->assertRedirect();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'first_name' => 'Sara']);
    }

    public function test_delete_archives_student_and_preserves_their_attendance(): void
    {
        $student = Student::create($this->details());
        $student->attendances()->create(['attendance_date' => '2026-09-30', 'status' => 'Present']);
        $this->delete(route('students.destroy', $student))->assertRedirect(route('students.index'));
        $this->assertSoftDeleted($student);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('student_attendances', 1);
        $this->assertSame(0, Student::count());
        $this->get(route('students.show', $student))->assertNotFound();
    }

    public function test_missing_photo_uses_initials_and_numeric_admission_is_formatted(): void
    {
        Storage::fake('public');
        $this->post(route('students.store'), $this->details(['admission_no' => '1']))->assertSessionHasNoErrors();
        $student = Student::firstOrFail();
        $this->assertSame('ADM-2026-0001', $student->admission_no);
        $student->update(['photo' => 'missing.png']);
        $this->get(route('students.index'))->assertOk()->assertSee('AK')->assertDontSee('missing.png')->assertSee('Grade 6');
    }

    public function test_blank_admission_is_generated_and_setup_seeding_is_repeatable(): void
    {
        $this->seed(\Database\Seeders\AcademicSetupSeeder::class);
        $this->seed(\Database\Seeders\AcademicSetupSeeder::class);
        $this->assertDatabaseHas('school_classes', ['name' => 'Nursery']);
        $this->assertDatabaseHas('school_classes', ['name' => 'Class 10']);
        $this->assertDatabaseHas('school_sections', ['name' => 'B']);
        $this->post(route('students.store'), $this->details(['admission_no' => '']))->assertSessionHasNoErrors();
        $student = Student::firstOrFail();
        $this->assertSame(sprintf('ADM-2026-%04d', $student->id), $student->admission_no);
    }
}

<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectMark;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubjectsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(\App\Models\User::factory()->create(['role' => 'Admin']));
    }

    private function details(array $overrides = []): array
    {
        return array_merge(['name' => 'English', 'code' => 'ENG', 'type' => 'theory', 'category' => 'compulsory', 'status' => 'Active', 'description' => 'Language skills'], $overrides);
    }

    private function row(array $overrides = []): array
    {
        return array_merge(['total_marks' => 100, 'pass_marks' => 33, 'practical_marks' => 0, 'is_compulsory' => 1, 'display_order' => 1], $overrides);
    }

    private function saveClass(SchoolClass $class, Subject $subject, array $row = [])
    {
        return $this->post(route('subjects.classes.store'), ['school_class_id' => $class->id, 'selected' => [$subject->id], 'subjects' => [$subject->id => $this->row($row)]]);
    }

    public function test_crud_unique_codes_filters_sorting_and_soft_delete(): void
    {
        $this->get(route('subjects.index'))->assertOk()->assertSee('Class-wise Subjects');
        $this->get(route('subjects.create'))->assertOk();
        $this->post(route('subjects.store'), $this->details(['code' => ' eng ']))->assertSessionHasNoErrors();
        $subject = Subject::firstOrFail();
        $this->assertSame('ENG', $subject->code);
        $this->post(route('subjects.store'), $this->details(['name' => 'Duplicate']))->assertSessionHasErrors('code');
        $this->post(route('subjects.store'), $this->details(['name' => 'Practical', 'code' => 'PRA', 'type' => 'invalid']))->assertSessionHasErrors('type');
        $this->put(route('subjects.update', $subject), $this->details(['name' => 'English Language', 'status' => 'Inactive']))->assertSessionHasNoErrors();
        $this->get(route('subjects.edit', $subject))->assertOk()->assertSee('English Language');
        $this->get(route('subjects.index', ['search' => 'ENG', 'type' => 'theory', 'status' => 'Inactive', 'sort' => 'code', 'direction' => 'desc']))->assertOk()->assertSee('English Language');
        $this->get(route('subjects.index', ['status' => 'Active']))->assertDontSee('English Language');
        $this->delete(route('subjects.destroy', $subject))->assertSessionHas('success');
        $this->assertSoftDeleted($subject);
        $this->post(route('subjects.store'), $this->details(['name' => 'New English']))->assertSessionHasErrors('code');
        $this->get(route('subjects.index', ['sort' => 'invalid']))->assertSessionHasErrors('sort');
    }

    public function test_class_selection_marks_validation_and_count(): void
    {
        $class = SchoolClass::create(['name' => 'Class 1']);
        $subject = Subject::create($this->details());
        $inactive = Subject::create($this->details(['name' => 'Hidden Subject', 'code' => 'HID', 'status' => 'Inactive']));
        $this->get(route('subjects.classes.index', ['school_class_id' => $class->id]))->assertOk()->assertSee('English')->assertDontSee('Hidden Subject');
        $this->saveClass($class, $subject, ['pass_marks' => 101])->assertSessionHasErrors('subjects.0.pass_marks');
        $this->assertDatabaseCount('class_subject', 0);
        $this->saveClass($class, $subject, ['practical_marks' => 101])->assertSessionHasErrors('subjects.0.practical_marks');
        $this->saveClass($class, $subject, ['total_marks' => 75, 'pass_marks' => 25, 'practical_marks' => 15, 'display_order' => 3])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('class_subject', ['school_class_id' => $class->id, 'subject_id' => $subject->id, 'total_marks' => 75, 'pass_marks' => 25, 'display_order' => 3]);
        $this->assertEquals(1, Subject::withCount('classes')->find($subject->id)->classes_count);
        $this->saveClass($class, $inactive)->assertSessionHasErrors('selected.0');
        $this->delete(route('subjects.destroy', $subject))->assertSessionHas('error');
        $this->assertNotSoftDeleted($subject);
        $this->post(route('subjects.classes.store'), ['school_class_id' => $class->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('class_subject', 0);
    }

    public function test_copy_replaces_active_links_preserves_inactive_and_settings(): void
    {
        $source = SchoolClass::create(['name' => 'Class 1']);
        $target = SchoolClass::create(['name' => 'Class 2']);
        $english = Subject::create($this->details());
        $other = Subject::create($this->details(['name' => 'Science', 'code' => 'SCI']));
        $inactive = Subject::create($this->details(['name' => 'Hidden', 'code' => 'HID', 'status' => 'Inactive']));
        $source->subjects()->attach($english->id, $this->row(['total_marks' => 80, 'pass_marks' => 24, 'practical_marks' => 20, 'is_compulsory' => 0, 'display_order' => 5]));
        $target->subjects()->attach($other->id, $this->row());
        $target->subjects()->attach($inactive->id, $this->row());
        $this->post(route('subjects.classes.copy'), ['school_class_id' => $target->id, 'source_class_id' => $source->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('class_subject', ['school_class_id' => $target->id, 'subject_id' => $english->id, 'total_marks' => 80, 'pass_marks' => 24, 'is_compulsory' => 0, 'display_order' => 5]);
        $this->assertDatabaseMissing('class_subject', ['school_class_id' => $target->id, 'subject_id' => $other->id]);
        $this->assertDatabaseHas('class_subject', ['school_class_id' => $target->id, 'subject_id' => $inactive->id]);
        $this->post(route('subjects.classes.copy'), ['school_class_id' => $target->id, 'source_class_id' => $target->id])->assertSessionHasErrors('source_class_id');
    }

    public function test_marks_block_delete_and_class_removal_with_transaction_rollback(): void
    {
        $class = SchoolClass::create(['name' => 'Class 1']);
        $source = SchoolClass::create(['name' => 'Class 2']);
        $english = Subject::create($this->details());
        $science = Subject::create($this->details(['name' => 'Science', 'code' => 'SCI']));
        $class->subjects()->attach($english->id, $this->row());
        $source->subjects()->attach($science->id, $this->row());
        $student = Student::create(['first_name' => 'Ali', 'last_name' => 'Khan', 'father_name' => 'Ahmed', 'admission_no' => 'MARK-001', 'roll_number' => '1', 'date_of_birth' => '2015-01-01', 'gender' => 'Male', 'phone' => '03001234567', 'address' => 'School Road', 'admission_date' => '2026-01-01', 'status' => 'Active']);
        SubjectMark::create(['subject_id' => $english->id, 'school_class_id' => $class->id, 'student_id' => $student->id, 'assessment' => 'Midterm', 'marks' => 65]);
        $this->post(route('subjects.classes.copy'), ['school_class_id' => $class->id, 'source_class_id' => $source->id])->assertSessionHasErrors('subjects');
        $this->assertDatabaseHas('class_subject', ['school_class_id' => $class->id, 'subject_id' => $english->id]);
        $this->assertDatabaseMissing('class_subject', ['school_class_id' => $class->id, 'subject_id' => $science->id]);
        $class->subjects()->detach(); // Verify the independent marks guard.
        $this->delete(route('subjects.destroy', $english))->assertSessionHas('error');
        $this->assertNotSoftDeleted($english);
    }

    public function test_seeder_is_repeatable_and_does_not_overwrite_settings(): void
    {
        $this->seed(SubjectSeeder::class);
        $this->assertDatabaseCount('subjects', 12);
        $this->assertDatabaseCount('school_classes', 10);
        $this->assertDatabaseCount('class_subject', 77);
        $row = DB::table('class_subject')->first();
        DB::table('class_subject')->where('id', $row->id)->update(['total_marks' => 50, 'pass_marks' => 20]);
        $this->seed(SubjectSeeder::class);
        $this->assertDatabaseCount('subjects', 12);
        $this->assertDatabaseCount('class_subject', 77);
        $this->assertDatabaseHas('class_subject', ['id' => $row->id, 'total_marks' => 50, 'pass_marks' => 20]);
    }

    public function test_paginated_directory_keeps_query_and_classes_count_sort(): void
    {
        for ($i = 1; $i <= 17; $i++) {
            Subject::create($this->details(['name' => 'Subject '.$i, 'code' => 'S'.$i]));
        }
        $response = $this->get(route('subjects.index', ['search' => 'Subject', 'sort' => 'classes_count', 'direction' => 'desc']));
        $response->assertOk();
        $this->assertEquals(17,$response['subjects']->total());
        $this->assertStringContainsString('sort=classes_count',$response['subjects']->nextPageUrl());
    }
}

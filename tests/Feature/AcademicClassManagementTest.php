<?php

namespace Tests\Feature;

use App\Models\{SchoolClass, Subject, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicClassManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_editor_and_rename_a_class(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'Admin']));
        $class = SchoolClass::create(['name'=>'Class 1']);
        $this->get(route('academic-setup.index'))->assertOk()->assertSee('Edit Class')->assertSee('Delete Class');
        $this->get(route('academic-setup.classes.edit',$class))->assertOk()->assertSee('Class 1');
        $this->put(route('academic-setup.classes.update',$class),['name'=>'  Class   One  '])->assertSessionHasNoErrors()->assertRedirect(route('academic-setup.index'));
        $this->assertSame('Class One',$class->fresh()->name);
        $this->put(route('academic-setup.classes.update',$class),['name'=>'Class One'])->assertSessionHasNoErrors();
    }

    public function test_duplicate_and_blank_class_names_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'Admin']));
        $class = SchoolClass::create(['name'=>'Class 1']);
        SchoolClass::create(['name'=>'Class 2']);
        foreach (['Class 2','   '] as $name) {
            $this->put(route('academic-setup.classes.update',$class),['name'=>$name])->assertSessionHasErrors('name');
        }
        $this->assertSame('Class 1',$class->fresh()->name);
    }

    public function test_an_unused_class_can_be_deleted(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'Admin']));
        $class = SchoolClass::create(['name'=>'Unused Class']);
        $this->delete(route('academic-setup.classes.destroy',$class))->assertSessionHasNoErrors()->assertRedirect(route('academic-setup.index'));
        $this->assertDatabaseMissing('school_classes',['id'=>$class->id]);
    }

    public function test_class_with_linked_subjects_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'Admin']));
        $class = SchoolClass::create(['name'=>'Class 1']);
        $subject = Subject::create(['name'=>'English','code'=>'ENG','status'=>'Active']);
        $class->subjects()->attach($subject->id);
        $this->delete(route('academic-setup.classes.destroy',$class))->assertSessionHasErrors('class');
        $this->assertDatabaseHas('school_classes',['id'=>$class->id]);
        $this->assertDatabaseHas('class_subject',['school_class_id'=>$class->id,'subject_id'=>$subject->id]);
    }

    public function test_teacher_cannot_modify_classes(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'Teacher']));
        $class = SchoolClass::create(['name'=>'Class 1']);
        $this->get(route('academic-setup.classes.edit',$class))->assertForbidden();
        $this->put(route('academic-setup.classes.update',$class),['name'=>'Changed'])->assertForbidden();
        $this->delete(route('academic-setup.classes.destroy',$class))->assertForbidden();
        $this->assertSame('Class 1',$class->fresh()->name);
    }
}

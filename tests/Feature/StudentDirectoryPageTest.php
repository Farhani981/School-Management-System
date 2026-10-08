<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesFeeFixtures;
use Tests\TestCase;

class StudentDirectoryPageTest extends TestCase
{
    use RefreshDatabase, CreatesFeeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'Admin']));
    }

    public function test_list_components_active_default_eager_relations_and_class_section_counts(): void
    {
        $section = SchoolSection::create(['name' => 'B']);
        $active = $this->feeStudent(['section_id' => $section->id]);
        $this->feeStudent(['section_id' => $section->id, 'status' => 'Left']);
        $this->feeStudent(['class_id' => null, 'section_id' => null]);
        $response = $this->get('/students')->assertOk()->assertSee('Student List')->assertSee('Rows:')->assertSee('Unassigned')->assertSee('Roll No:')->assertSee('Print ID Card');
        $this->assertSame(2, $response['students']->total());
        $this->assertSame(1, $response['directoryCounts']['classes'][$active->class_id]);
        $this->assertTrue($response['students']->first()->relationLoaded('schoolClass'));
        $this->assertTrue($response['students']->first()->relationLoaded('section'));
        $classPage = $this->get('/students?class_id='.$active->class_id)->assertOk()->assertSee('id="filter-class_id"', false)->assertSee('id="filter-section_id"', false)->assertDontSee('Browse by class');
        $this->assertSame(1, $classPage['directoryCounts']['sections'][$section->id]);
        $this->get('/students?status=')->assertViewHas('students', fn ($rows) => $rows->total() === 3);
        $this->get('/students?search=no-such-name')->assertOk()->assertSee('No students found')->assertSee('Clear filters');
    }

    public function test_class_and_status_sorting_pagination_and_one_cached_grouped_query(): void
    {
        $one = $this->feeStudent();
        $two = $this->feeStudent(['class_id' => SchoolClass::create(['name' => 'Class 2'])->id, 'status' => 'Left']);
        $this->get('/students?status=&sort=class&direction=desc&per_page=100')->assertViewHas('students', fn ($rows) => $rows->first()->id === $two->id && $rows->perPage() === 100);
        $this->get('/students?status=&sort=status&direction=asc')->assertViewHas('students', fn ($rows) => $rows->first()->id === $one->id);
        $this->get('/students?per_page=1000')->assertSessionHasErrors('per_page');
        Cache::flush();
        DB::enableQueryLog(); DB::flushQueryLog();
        $directory = app(StudentDirectory::class);
        $directory->counts(['status' => 'Active']);
        $directory->counts(['status' => 'Active']);
        $grouped = collect(DB::getQueryLog())->filter(fn ($query) => str_contains(strtolower($query['query']), 'group by'));
        $this->assertCount(1, $grouped);
        DB::disableQueryLog();
        $one->update(['status' => 'Left']);
        $this->assertSame(0, $directory->counts(['status' => 'Active'])['total']);
    }

    public function test_serial_numbers_continue_across_pages_and_delete_is_soft(): void
    {
        for ($i=0; $i<12; $i++) $this->feeStudent();
        $response = $this->get('/students?page=2&per_page=10')->assertOk()->assertSee('Showing 11 to 12 of 12 students');
        $this->assertSame(11, $response['students']->firstItem());
        $this->assertMatchesRegularExpression('/directory-serial">11<\/td>/', $response->getContent());
        $student = Student::first();
        $this->delete(route('students.destroy', $student))->assertRedirect(route('students.index'));
        $this->assertSoftDeleted($student);
    }
}

<?php

namespace Tests\Feature;

use App\Models\{AcademicSession, Exam, ExamSchedule, SchoolSection, Subject, TeacherAssignment, User};
use App\Services\ExamSetup;
use Database\Seeders\TeacherPanelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamSetupTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private TeacherAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TeacherPanelSeeder::class);
        $this->admin = User::factory()->create(['role'=>'Admin']);
        $this->assignment = TeacherAssignment::with('session')->firstOrFail();
        AcademicSession::whereKey($this->assignment->session_id)->update(['is_current'=>true]);
        $this->assignment->schoolClass->subjects()->syncWithoutDetaching([$this->assignment->subject_id=>[
            'total_marks'=>100,'pass_marks'=>40,'practical_marks'=>0,'is_compulsory'=>true,'display_order'=>1]]);
        $this->actingAs($this->admin);
    }

    private function details(array $overrides = []): array
    {
        return array_replace(['name'=>'Final Term 2026', 'type'=>'final', 'session_id'=>$this->assignment->session_id,
            'start_date'=>'2026-10-12', 'end_date'=>'2026-10-20', 'description'=>'Official term examination',
            'participants'=>[$this->assignment->class_id.':'.$this->assignment->section_id]], $overrides);
    }

    private function exam(): Exam
    {
        $this->post(route('exams.store'),$this->details())->assertSessionHasNoErrors();
        return Exam::latest('id')->firstOrFail();
    }

    private function subjectData(array $overrides = []): array
    {
        return ['class_id'=>$this->assignment->class_id, 'selected'=>[$this->assignment->subject_id],
            'subjects'=>[$this->assignment->subject_id=>array_replace(['total_marks'=>100,'passing_marks'=>40,
                'teachers'=>[$this->assignment->section_id=>$this->assignment->teacher_id]],$overrides)]];
    }

    private function simpleData(array $overrides = []): array
    {
        return ['action'=>'publish','classes'=>[$this->assignment->class_id=>[
            'selected'=>[$this->assignment->subject_id],
            'subjects'=>[$this->assignment->subject_id=>array_replace([
                'theory_marks'=>80,'practical_marks'=>20,'passing_marks'=>33,'exam_date'=>'2026-10-12',
                'start_time'=>'09:00','end_time'=>'10:00','teachers'=>[$this->assignment->section_id=>$this->assignment->teacher_id],
            ],$overrides)],
        ]]];
    }

    public function test_simple_datesheet_selects_exam_teacher_without_creating_school_assignments(): void
    {
        $exam=$this->exam();
        $teacher=$this->assignment->teacher->user;
        $this->assignment->delete();
        $count=TeacherAssignment::count();
        $this->get(route('exams.show',$exam))->assertOk()->assertSee('Create the datesheet')->assertSee($teacher->name)->assertDontSee('Manage Teacher Assignments');
        $this->post(route('exams.datesheet.save',$exam),$this->simpleData())->assertSessionHasNoErrors();
        $this->assertSame($count,TeacherAssignment::count());
        $this->assertNotNull($exam->fresh()->datesheet_published_at);
        $this->assertDatabaseHas('exam_subjects',['exam_id'=>$exam->id,'theory_marks'=>80,'practical_marks'=>20,'total_marks'=>100]);
        $this->actingAs($teacher)->get(route('teacher.exams.index'))->assertOk()->assertSee($exam->name)->assertSee('Enter Marks');
        $paper=$exam->schedules()->firstOrFail();
        $this->get(route('teacher.exams.marks.edit',$paper))->assertOk()->assertSee('Practical / 20.00');
    }

    public function test_same_subject_in_two_classes_publishes_and_reaches_teacher_without_duplicate_assignments(): void
    {
        $secondClass=\App\Models\SchoolClass::create(['name'=>'Regression Class 2']);
        $secondClass->subjects()->attach($this->assignment->subject_id,['total_marks'=>100,'pass_marks'=>33,'practical_marks'=>20,'is_compulsory'=>true,'display_order'=>1]);
        TeacherAssignment::create(['class_id'=>$secondClass->id,'section_id'=>$this->assignment->section_id,
            'subject_id'=>$this->assignment->subject_id,'teacher_id'=>$this->assignment->teacher_id,'session_id'=>$this->assignment->session_id]);
        $this->post(route('exams.store'),$this->details(['participants'=>[
            $this->assignment->class_id.':'.$this->assignment->section_id,$secondClass->id.':'.$this->assignment->section_id,
        ]]))->assertSessionHasNoErrors();
        $exam=Exam::latest('id')->firstOrFail();
        $data=$this->simpleData();
        $data['classes'][$secondClass->id]=$data['classes'][$this->assignment->class_id];
        $count=TeacherAssignment::count();
        $this->post(route('exams.datesheet.save',$exam),$data)->assertSessionHasNoErrors();
        $this->assertNotNull($exam->fresh()->datesheet_published_at);
        $this->assertSame(2,$exam->subjects()->where('subject_id',$this->assignment->subject_id)->count());
        $this->assertSame(2,$exam->schedules()->count());
        $this->assertSame($count,TeacherAssignment::count());
        $teacher=$this->assignment->teacher->user;
        $this->assertSame(2,ExamSchedule::forTeacher($teacher)->count());
        $this->actingAs($teacher)->get(route('teacher.exams.index'))->assertOk()->assertSee($secondClass->name)->assertSee('Enter Marks')->assertDontSee('Awaiting datesheet publication');
    }

    public function test_duplicate_subject_within_one_class_is_rejected_without_saving_or_publishing(): void
    {
        $exam=$this->exam(); $data=$this->simpleData();
        $data['classes'][$this->assignment->class_id]['selected'][]=$this->assignment->subject_id;
        $this->post(route('exams.datesheet.save',$exam),$data)->assertSessionHasErrors('setup');
        $this->assertNull($exam->fresh()->datesheet_published_at);
        $this->assertSame(0,$exam->subjects()->count());
        $this->assertSame(0,$exam->schedules()->count());
    }

    public function test_simple_datesheet_draft_can_be_edited_and_published_without_duplicates(): void
    {
        $exam=$this->exam(); $data=$this->simpleData(); $data['action']='save';
        $this->post(route('exams.datesheet.save',$exam),$data)->assertSessionHasNoErrors();
        $this->assertNull($exam->fresh()->datesheet_published_at);
        $this->get(route('exams.show',$exam))->assertOk()->assertSee('Send to Teachers');
        $data['action']='publish';
        $this->post(route('exams.datesheet.save',$exam),$data)->assertSessionHasNoErrors();
        $this->assertSame(1,$exam->subjects()->count());
        $this->assertSame(1,$exam->schedules()->count());
        $this->post(route('exams.datesheet.save',$exam),$data)->assertSessionHasErrors();
    }

    public function test_simple_exam_submissions_combine_theory_practical_and_pdf_report_cards(): void
    {
        $exam=$this->exam();
        $this->post(route('exams.datesheet.save',$exam),$this->simpleData())->assertSessionHasNoErrors();
        $paper=$exam->schedules()->firstOrFail(); $teacher=$this->assignment->teacher->user;
        $this->actingAs($teacher)->get(route('teacher.exams.marks.edit',$paper))->assertOk();
        $roster=$paper->fresh()->quiz->examRosterEntries()->get();
        $rows=$roster->map(fn($e)=>['student_id'=>$e->student_id,'attendance_status'=>'present','theory_marks'=>60,'practical_marks'=>15,'public_remark'=>'Good progress.'])->all();
        $invalid=$rows; $invalid[0]['practical_marks']=21;
        $this->post(route('teacher.exams.marks.save',$paper),['rows'=>$invalid,'action'=>'submit'])->assertSessionHasErrors();
        $this->assertSame(0,$paper->fresh()->quiz->results()->count());
        $this->post(route('teacher.exams.marks.save',$paper),['rows'=>$rows,'action'=>'submit'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('quiz_results',['quiz_id'=>$paper->fresh()->quiz->id,'marks_obtained'=>75,'theory_marks'=>60,'practical_marks'=>15]);
        $student=\App\Models\Student::findOrFail($rows[0]['student_id']);
        $this->actingAs($this->admin)->get(route('exams.results.index',$exam))->assertOk()->assertSee($student->full_name)->assertSee('75%')->assertSee('PASS');
        $this->get(route('exams.results.show',[$exam,$student]))->assertOk()->assertSee('PROGRESS REPORT CARD')->assertSee('Good progress.')->assertSee('75 / 100');
        $this->get(route('exams.results.show',[$exam,$student,'download'=>'pdf']))->assertDownload('report-'.$exam->id.'-'.$student->id.'.pdf');
        $this->actingAs($teacher)->get(route('exams.results.show',[$exam,$student]))->assertForbidden();
    }

    public function test_exam_csv_upload_submits_marks_and_rejects_duplicate_students(): void
    {
        $exam=$this->exam();
        $this->post(route('exams.datesheet.save',$exam),$this->simpleData())->assertSessionHasNoErrors();
        $paper=$exam->schedules()->firstOrFail();
        $this->actingAs($this->assignment->teacher->user)->get(route('teacher.exams.marks.edit',$paper))->assertOk();
        $roster=$paper->fresh()->quiz->examRosterEntries()->get();
        $this->get(route('teacher.exams.marks.edit',[$paper,'template'=>'csv']))->assertDownload('exam-marks-template.csv');
        $csv="admission_no,marks_obtained,theory_marks,practical_marks,attendance_status,public_remark\n";
        foreach ($roster as $entry) $csv.=$entry->admission_no.",,50,10,present,Well done\n";
        $duplicate=$csv.$roster->first()->admission_no.",,50,10,present,Well done\n";
        $this->post(route('teacher.exams.marks.import',$paper),['file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('marks.csv',$duplicate)])->assertSessionHasErrors();
        $this->assertSame('not_started',$paper->fresh()->marks_status);
        $this->post(route('teacher.exams.marks.import',$paper),['file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('marks.csv',$csv)])->assertSessionHasNoErrors();
        $this->assertSame('submitted',$paper->fresh()->marks_status);
    }

    public function test_two_subjects_combine_only_submitted_marks_and_rank_complete_students(): void
    {
        $exam=$this->exam();
        $extra=Subject::create(['name'=>'Science','code'=>'SCI','status'=>'Active']);
        $this->assignment->schoolClass->subjects()->attach($extra->id,['total_marks'=>100,'pass_marks'=>33,'practical_marks'=>0,'is_compulsory'=>true,'display_order'=>2]);
        $data=$this->simpleData();
        $data['classes'][$this->assignment->class_id]['selected'][]=$extra->id;
        $data['classes'][$this->assignment->class_id]['subjects'][$extra->id]=[
            'theory_marks'=>100,'practical_marks'=>0,'passing_marks'=>33,'exam_date'=>'2026-10-12',
            'start_time'=>'10:00','end_time'=>'11:00','teachers'=>[$this->assignment->section_id=>$this->assignment->teacher_id],
        ];
        $this->post(route('exams.datesheet.save',$exam),$data)->assertSessionHasNoErrors();
        $papers=$exam->schedules()->orderBy('id')->get();
        $this->actingAs($this->assignment->teacher->user);
        foreach ($papers as $position=>$paper) {
            $this->get(route('teacher.exams.marks.edit',$paper))->assertOk();
            $roster=$paper->fresh()->quiz->examRosterEntries()->orderBy('id')->get();
            $rows=$roster->map(fn($entry,$i)=>[
                'student_id'=>$entry->student_id,'attendance_status'=>'present',
                'theory_marks'=>$i===0 ? 60 : 50,'practical_marks'=>15,'marks_obtained'=>$i===0 ? 80 : 60,
            ])->all();
            $this->post(route('teacher.exams.marks.save',$paper),['rows'=>$rows,'action'=>'draft'])->assertSessionHasNoErrors();
            if ($position===1) {
                $reports=app(\App\Services\ExamResults::class)->all($exam->fresh());
                $this->assertFalse($reports[0]['complete']);
                $this->assertNull($reports[0]['rank']);
            }
            $this->post(route('teacher.exams.marks.save',$paper),['rows'=>$rows,'action'=>'submit'])->assertSessionHasNoErrors();
        }
        $reports=collect(app(\App\Services\ExamResults::class)->all($exam->fresh()))->keyBy('student.id');
        $first=$reports[$rows[0]['student_id']];
        $this->assertTrue($first['complete']);
        $this->assertEquals(155,$first['total']);
        $this->assertEquals(200,$first['maximum']);
        $this->assertEquals(77.5,$first['percentage']);
        $this->assertSame(1,$first['rank']);
        $this->assertSame(2,count($first['rows']));
        $this->assertSame(2,$reports[$rows[1]['student_id']]['rank']);
    }

    public function test_practical_draft_retains_partial_components_and_rejects_incomplete_submission(): void
    {
        $exam=$this->exam();
        $this->post(route('exams.datesheet.save',$exam),$this->simpleData())->assertSessionHasNoErrors();
        $paper=$exam->schedules()->firstOrFail();
        $this->actingAs($this->assignment->teacher->user)->get(route('teacher.exams.marks.edit',$paper))->assertOk();
        $studentId=$paper->fresh()->quiz->examRosterEntries()->firstOrFail()->student_id;
        $rows=[['student_id'=>$studentId,'attendance_status'=>'present','theory_marks'=>60,'practical_marks'=>null]];
        $this->post(route('teacher.exams.marks.save',$paper),['rows'=>$rows,'action'=>'draft'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('quiz_results',['quiz_id'=>$paper->fresh()->quiz->id,'student_id'=>$studentId,'theory_marks'=>60,'practical_marks'=>null,'marks_obtained'=>null]);
        $this->post(route('teacher.exams.marks.save',$paper),['rows'=>$rows,'action'=>'submit'])->assertSessionHasErrors();
        $this->assertSame('draft',$paper->fresh()->marks_status);
    }

    public function test_admin_creates_exam_configures_subjects_and_views_all_setup_pages(): void
    {
        $this->get(route('exams.index'))->assertOk()->assertSee('Create Examination')->assertDontSee('@class')->assertSee('id="exams-submenu"',false);
        $this->get(route('exams.create'))->assertOk()->assertSee('Participating classes');
        $exam = $this->exam();
        $this->assertDatabaseHas('exam_participations',['exam_id'=>$exam->id,'class_id'=>$this->assignment->class_id,'section_id'=>$this->assignment->section_id]);
        $this->get(route('exams.show',$exam))->assertOk()->assertSee('Create the datesheet');
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasNoErrors();
        $config = $exam->subjects()->firstOrFail();
        $this->assertDatabaseHas('exam_subject_assignments',['exam_subject_id'=>$config->id,'section_id'=>$this->assignment->section_id,'teacher_id'=>$this->assignment->teacher_id]);
        $this->get(route('exams.show',$exam))->assertOk()->assertSee('Send to Teachers');
        $this->get(route('exams.edit',$exam))->assertOk()->assertSee('Edit Exam');
        $this->put(route('exams.update',$exam),$this->details(['name'=>'Updated Final']))->assertSessionHasNoErrors();
        $this->get(route('exams.index',['search'=>'Updated Final','session_id'=>$exam->session_id]))->assertOk()->assertSee('Updated Final');
        $this->assertDatabaseHas('exam_logs',['exam_id'=>$exam->id,'actor_id'=>$this->admin->id,'action'=>'exam_subjects_saved']);
    }

    public function test_assigned_teacher_sees_unpublished_exam_status_without_marks_access(): void
    {
        $exam = $this->exam();
        $this->post(route('exams.subjects.store', $exam), $this->subjectData())->assertSessionHasNoErrors();
        $this->get(route('exams.index'))->assertOk()->assertSee('0 paper dates saved');
        $teacher = $this->assignment->teacher->user;
        $this->actingAs($teacher);
        foreach (['teacher.exams.index', 'teacher.dashboard'] as $route) {
            $this->get(route($route))->assertOk()->assertSee($exam->name)->assertSee('Awaiting datesheet publication')->assertDontSee('>Enter Marks<', false);
        }
        $this->get(route('teacher.exams.schedule'))->assertOk()->assertViewIs('teacher.exams.schedule')
            ->assertDontSee($exam->name)->assertSee('No published papers assigned yet');
        $this->assignment->delete();
        $this->get(route('teacher.exams.index'))->assertOk()->assertSee($exam->name)->assertSee('Awaiting datesheet publication');
        $exam->subjects()->first()->assignments()->delete();
        $this->get(route('teacher.exams.index'))->assertOk()->assertDontSee($exam->name);
    }

    public function test_class_setup_preselects_existing_subject_teacher_and_continues_to_datesheet(): void
    {
        $exam = $this->exam();
        $this->get(route('exams.show',$exam))->assertOk()->assertSee('Create the datesheet')->assertSee('Select teacher')->assertSee($this->assignment->teacher->user->name);
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasNoErrors()->assertRedirect(route('exams.schedule.index',$exam));
    }

    public function test_load_existing_teachers_saves_subject_setup_and_is_idempotent(): void
    {
        $exam=$this->exam();
        $this->get(route('exams.show',$exam))->assertOk()->assertSee('Create the datesheet');
        $this->post(route('exams.teachers.load',$exam))->assertSessionHasNoErrors();
        $subject=$exam->subjects()->where('subject_id',$this->assignment->subject_id)->firstOrFail();
        $this->assertEquals($this->assignment->teacher_id,$subject->assignments()->firstOrFail()->teacher_id);
        $before=$exam->subjects()->count();
        $this->post(route('exams.teachers.load',$exam))->assertSessionHasNoErrors();
        $this->assertEquals($before,$exam->subjects()->count());
        $exam->update(['status'=>'schedule_published','datesheet_published_at'=>now()]);
        $this->post(route('exams.teachers.load',$exam))->assertSessionHasErrors();
    }

    public function test_simple_exam_form_saves_times_validates_order_and_exports_list(): void
    {
        $data=$this->details(['start_time'=>'10:00','end_time'=>'13:00']);
        unset($data['end_date']);
        $this->post(route('exams.store'),$data)->assertSessionHasNoErrors();
        $exam=Exam::latest('id')->firstOrFail();
        $this->assertEquals($exam->start_date->toDateString(),$exam->end_date->toDateString());
        $this->assertEquals('10:00', substr($exam->fresh()->start_time,0,5));
        $this->get(route('exams.index',['per_page'=>25]))->assertOk()->assertSee('10:00 AM')->assertSee('01:00 PM');
        $this->get(route('exams.index',['export'=>'csv']))->assertDownload('exams.csv');
        $this->post(route('exams.store'),array_replace($data,['end_time'=>'09:00']))->assertSessionHasErrors('end_time');
    }

    public function test_single_schedule_form_saves_and_lists_a_configured_paper(): void
    {
        $exam=$this->exam();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasNoErrors();
        $subject=$exam->subjects()->firstOrFail();
        $this->get(route('exams.schedule.index',[$exam,'add'=>1]))->assertRedirect(route('exams.show',$exam));
        $this->post(route('exams.schedule.store',$exam),['slot'=>$subject->id.':'.$this->assignment->section_id,'exam_date'=>'2026-10-12','start_time'=>'10:00','end_time'=>'13:00','room'=>'101','invigilator_id'=>$this->assignment->teacher_id,'marks_deadline'=>'2026-10-13T17:00'])->assertSessionHasNoErrors()->assertRedirect(route('exams.schedule.index',$exam));
        $paper=$exam->schedules()->firstOrFail();
        $this->get(route('exams.schedule.index',[$exam,'search'=>$subject->subject->name]))->assertRedirect(route('exams.show',$exam));
        $this->get(route('exams.schedule.index',[$exam,'paper'=>$paper->id]))->assertRedirect(route('exams.show',$exam));
        $this->get(route('exams.schedule.index',[$exam,'export'=>'csv']))->assertDownload('exam-schedule.csv');
    }

    public function test_invalid_dates_sessions_and_class_section_pairs_do_not_create_exams(): void
    {
        $this->post(route('exams.store'),$this->details(['end_date'=>'2026-10-01']))->assertSessionHasErrors('end_date');
        $past = AcademicSession::create(['name'=>'Archived','is_current'=>false]);
        $this->post(route('exams.store'),$this->details(['session_id'=>$past->id]))->assertSessionHasErrors('session_id');
        $other = SchoolSection::create(['name'=>'Unassigned']);
        $this->post(route('exams.store'),$this->details(['participants'=>[$this->assignment->class_id.':'.$other->id]]))->assertSessionHasErrors('participants');
        $this->post(route('exams.store'),$this->details(['participants'=>[]]))->assertSessionHasErrors('participants');
        $this->post(route('exams.store'),$this->details(['participants'=>[$this->assignment->class_id.':'.$this->assignment->section_id,$this->assignment->class_id.':'.$this->assignment->section_id]]))->assertSessionHasErrors();
        $this->assertDatabaseCount('exams',0);
    }

    public function test_invalid_marks_and_wrong_teacher_are_rejected_without_overwriting_saved_setup(): void
    {
        $exam=$this->exam();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasNoErrors();
        foreach ([['passing_marks'=>101],['total_marks'=>0],['total_marks'=>-1],['total_marks'=>80.123],['teachers'=>[]]] as $invalid) {
            $this->post(route('exams.subjects.store',$exam),$this->subjectData($invalid))->assertSessionHasErrors();
        }
        $otherSection=SchoolSection::create(['name'=>'Other section']);
        $otherData=$this->subjectData(['teachers'=>[$otherSection->id=>$this->assignment->teacher_id]]);
        $this->post(route('exams.subjects.store',$exam),$otherData)->assertSessionHasErrors();
        $this->assertEquals(100,$exam->subjects()->first()->total_marks);
        $this->assertEquals($this->assignment->teacher_id,$exam->subjects()->first()->assignments()->first()->teacher_id);
    }

    public function test_unassigned_subject_is_rejected_and_teacher_assignment_removal_updates_checklist(): void
    {
        $exam=$this->exam();
        $subject=Subject::create(['name'=>'Not in class','code'=>'OUT','status'=>'Active','type'=>'theory','category'=>'compulsory']);
        $data=$this->subjectData();$data['selected']=[$subject->id];$data['subjects']=[$subject->id=>$data['subjects'][$this->assignment->subject_id]];
        $this->post(route('exams.subjects.store',$exam),$data)->assertSessionHasErrors();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasNoErrors();
        $this->assertSame([],app(ExamSetup::class)->checklist($exam->fresh()));
        $this->assignment->delete();
        $this->assertSame([],app(ExamSetup::class)->checklist($exam->fresh()));
        $exam->subjects()->first()->assignments()->delete();
        $this->assertNotEmpty(app(ExamSetup::class)->checklist($exam->fresh()));
    }

    public function test_scheduled_or_published_setup_is_protected_and_historical_statuses_stay_readable(): void
    {
        $exam=$this->exam();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasNoErrors();
        ExamSchedule::create(['exam_subject_id'=>$exam->subjects()->first()->id,'section_id'=>$this->assignment->section_id,
            'teacher_id'=>$this->assignment->teacher_id,'exam_date'=>'2026-10-12','start_time'=>'09:00','end_time'=>'11:00','paper_deadline'=>'2026-10-10']);
        $this->post(route('exams.subjects.store',$exam),$this->subjectData(['total_marks'=>80]))->assertSessionHasNoErrors();
        $exam->update(['status'=>'published','result_published_at'=>now()]);
        $this->put(route('exams.update',$exam),$this->details(['name'=>'Forbidden edit']))->assertSessionHasErrors();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData(['total_marks'=>90]))->assertSessionHasErrors();
        $this->get(route('exams.show',$exam))->assertOk()->assertDontSee('Send to Teachers');
        $this->assertSame('Result Published',$exam->fresh()->status_label);
        $this->post(route('exams.cancel',$exam),['reason'=>'Cancelled'])->assertSessionHasErrors();
        $this->assertDatabaseHas('exams',['id'=>$exam->id,'name'=>'Final Term 2026']);
    }

    public function test_cancel_requires_reason_logs_action_and_blocks_further_editing(): void
    {
        $exam=$this->exam();
        $this->post(route('exams.cancel',$exam),['reason'=>''])->assertSessionHasErrors('reason');
        $this->post(route('exams.cancel',$exam),['reason'=>'   '])->assertSessionHasErrors('reason');
        $this->post(route('exams.cancel',$exam),['reason'=>'School closure'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exam_logs',['exam_id'=>$exam->id,'action'=>'exam_cancelled','note'=>'School closure']);
        $this->put(route('exams.update',$exam),$this->details())->assertSessionHasErrors();
        $this->get(route('exams.show',$exam))->assertOk()->assertSee('Cancelled');
        $this->assertDatabaseCount('exams',1);
    }

    public function test_teacher_cannot_create_configure_or_cancel_formal_exams_but_quizzes_work(): void
    {
        $exam=$this->exam();
        $teacher=User::where('email','teacher@test.com')->firstOrFail();
        $this->actingAs($teacher);
        foreach ([route('exams.index'),route('exams.create'),route('exams.show',$exam),route('exams.edit',$exam)] as $url) $this->get($url)->assertForbidden();
        $this->post(route('exams.store'),$this->details())->assertForbidden();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertForbidden();
        $this->post(route('exams.cancel',$exam),['reason'=>'No permission'])->assertForbidden();
        $this->get(route('teacher.quizzes.index'))->assertOk();
    }

    public function test_subject_changes_are_atomic_and_inactive_teachers_cannot_be_selected(): void
    {
        $exam=$this->exam();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasNoErrors();
        $extra=Subject::create(['name'=>'Science','code'=>'SCI2','status'=>'Active','type'=>'theory','category'=>'compulsory']);
        $this->assignment->schoolClass->subjects()->attach($extra->id,['total_marks'=>100,'pass_marks'=>40,'practical_marks'=>0,'is_compulsory'=>true,'display_order'=>2]);
        $data=$this->subjectData(['total_marks'=>80]);
        $data['selected'][]=$extra->id;
        $data['subjects'][$extra->id]=['total_marks'=>100,'passing_marks'=>101,'teachers'=>[]];
        $this->post(route('exams.subjects.store',$exam),$data)->assertSessionHasErrors();
        $this->assertEquals(100,$exam->subjects()->first()->total_marks);
        $this->assertDatabaseCount('exam_subject_assignments',1);
        $this->assignment->teacher->update(['status'=>'Inactive']);
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasErrors();
    }

    public function test_principal_can_configure_exam_and_past_session_setup_is_read_only(): void
    {
        $principal=User::factory()->create(['role'=>'Principal']);
        $this->actingAs($principal);
        $exam=$this->exam();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasNoErrors();
        AcademicSession::whereKey($exam->session_id)->update(['is_current'=>false]);
        $this->put(route('exams.update',$exam),$this->details())->assertSessionHasErrors();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasErrors();
        $this->get(route('exams.show',$exam))->assertOk()->assertSee('read-only');
    }

    public function test_teacher_dashboard_and_schedule_show_only_owned_published_non_cancelled_papers(): void
    {
        $exam=$this->exam();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasNoErrors();
        ExamSchedule::create(['exam_subject_id'=>$exam->subjects()->first()->id,'section_id'=>$this->assignment->section_id,
            'teacher_id'=>$this->assignment->teacher_id,'exam_date'=>'2026-10-12','start_time'=>'09:00','end_time'=>'11:00',
            'paper_deadline'=>'2026-10-10','marks_deadline'=>'2026-10-20 17:00:00','marks_status'=>'returned','return_reason'=>'Please verify these marks.']);
        $teacher=User::where('email','teacher@test.com')->firstOrFail();
        $this->actingAs($teacher)->get(route('teacher.dashboard'))->assertOk()->assertSee('My Examinations')->assertSee($exam->name)->assertSee('Awaiting datesheet publication')->assertDontSee('Please verify these marks.');
        $exam->update(['datesheet_published_at'=>now(),'status'=>'schedule_published']);
        $this->get(route('teacher.dashboard'))->assertOk()->assertSee($exam->name);
        $this->get(route('teacher.exams.schedule', ['status'=>'submitted']))->assertOk()
            ->assertViewIs('teacher.exams.schedule')->assertSee($exam->name)->assertSee('12 Oct 2026')
            ->assertDontSee('Marks status')->assertDontSee('Submission deadline')->assertDontSee('Enter Marks')
            ->assertDontSee('Please verify these marks.');
        $this->get(route('teacher.exams.index'))->assertOk()->assertViewIs('teacher.exams.index')
            ->assertSee('Marks status')->assertSee('Submission deadline')->assertSee('Enter Marks')
            ->assertSee('Please verify these marks.');
        $this->get(route('teacher.exams.index',['status'=>'submitted']))->assertOk()->assertDontSee($exam->name);
        $outsider=User::factory()->create(['role'=>'Teacher']);
        $this->actingAs($outsider)->get(route('teacher.exams.index'))->assertOk()->assertDontSee($exam->name);
        $this->get(route('teacher.exams.schedule'))->assertOk()->assertDontSee($exam->name);
        $this->actingAs($teacher);
        $exam->update(['status'=>'cancelled']);
        $this->get(route('teacher.dashboard'))->assertOk()->assertDontSee($exam->name);
        $exam->update(['status'=>'schedule_published']);
        $this->assignment->delete();
        $this->get(route('teacher.exams.schedule'))->assertOk()->assertSee($exam->name);
        $exam->subjects()->first()->assignments()->delete();
        $this->get(route('teacher.exams.schedule'))->assertOk()->assertDontSee($exam->name);
    }

    public function test_loading_teachers_repairs_missing_exam_assignment_without_changing_marks(): void
    {
        $exam=$this->exam();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData(['total_marks'=>80,'passing_marks'=>32]))->assertSessionHasNoErrors();
        $subject=$exam->subjects()->firstOrFail();
        $subject->assignments()->delete();
        $this->post(route('exams.teachers.load',$exam))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exam_subject_assignments',['exam_subject_id'=>$subject->id,'section_id'=>$this->assignment->section_id,'teacher_id'=>$this->assignment->teacher_id]);
        $this->assertEquals('80.00',$subject->fresh()->total_marks);
        $this->assertEquals('32.00',$subject->fresh()->passing_marks);
        $this->assertSame([],app(ExamSetup::class)->checklist($exam->fresh()));
    }

    public function test_schedule_page_guides_incomplete_setup_before_showing_paper_form(): void
    {
        $exam=$this->exam();
        $this->get(route('exams.schedule.index',[$exam,'add'=>1]))->assertRedirect(route('exams.show',$exam));
        $this->get(route('exams.show',$exam))->assertOk()->assertSee('Create the datesheet')->assertDontSee('Assign Teachers');
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasNoErrors();
        $this->get(route('exams.show',$exam))->assertOk()->assertSee('Send to Teachers')->assertSee('data-simple-datesheet',false);
    }

    public function test_single_schedule_save_and_publish_delivers_paper_to_assigned_teacher(): void
    {
        $exam=$this->exam();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasNoErrors();
        $subject=$exam->subjects()->firstOrFail();
        $this->post(route('exams.schedule.store',$exam),[
            'slot'=>$subject->id.':'.$this->assignment->section_id,
            'exam_date'=>'2026-10-12','start_time'=>'09:00','end_time'=>'11:00','room'=>'101',
            'invigilator_id'=>$this->assignment->teacher_id,'marks_deadline'=>'2026-10-20 17:00','action'=>'publish',
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($exam->fresh()->datesheet_published_at);
        $teacher=User::where('email','teacher@test.com')->firstOrFail();
        $this->assertDatabaseHas('notifications',['notifiable_id'=>$teacher->id]);
        $this->actingAs($teacher)->get(route('teacher.exams.index'))->assertOk()->assertSee($exam->name)->assertSee('Enter Marks');
    }

    public function test_save_and_publish_incomplete_datesheet_rolls_back_the_new_paper(): void
    {
        $exam=$this->exam();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasNoErrors();
        $subject=$exam->subjects()->firstOrFail();
        $extra=Subject::create(['name'=>'Science','code'=>'SCI','status'=>'Active']);
        $this->assignment->schoolClass->subjects()->attach($extra->id,['total_marks'=>100,'pass_marks'=>40,'practical_marks'=>0,'is_compulsory'=>true,'display_order'=>2]);
        TeacherAssignment::create(['class_id'=>$this->assignment->class_id,'section_id'=>$this->assignment->section_id,'session_id'=>$exam->session_id,'subject_id'=>$extra->id,'teacher_id'=>$this->assignment->teacher_id]);
        $extraConfig=$exam->subjects()->create(['class_id'=>$this->assignment->class_id,'subject_id'=>$extra->id,'theory_marks'=>100,'total_marks'=>100,'passing_marks'=>40]);
        $extraConfig->assignments()->create(['section_id'=>$this->assignment->section_id,'teacher_id'=>$this->assignment->teacher_id]);
        $this->post(route('exams.schedule.store',$exam),[
            'slot'=>$subject->id.':'.$this->assignment->section_id,
            'exam_date'=>'2026-10-12','start_time'=>'09:00','end_time'=>'11:00','room'=>'101',
            'invigilator_id'=>$this->assignment->teacher_id,'marks_deadline'=>'2026-10-20 17:00','action'=>'publish',
        ])->assertSessionHasErrors();
        $this->assertNull($exam->fresh()->datesheet_published_at);
        $this->assertSame(0,$exam->schedules()->count());
    }

    public function test_schedule_publication_marks_draft_submit_return_resubmit_and_approval_work_together(): void
    {
        $exam=$this->exam();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasNoErrors();
        $subject=$exam->subjects()->firstOrFail();
        $this->get(route('exams.schedule.index',$exam))->assertRedirect(route('exams.show',$exam));
        $this->post(route('exams.schedule.publish',$exam))->assertSessionHasErrors();
        $key=$subject->id.':'.$this->assignment->section_id;
        $schedule=['exam_date'=>'2026-10-12','start_time'=>'09:00','end_time'=>'11:00','room'=>'101',
            'invigilator_id'=>$this->assignment->teacher_id,'marks_deadline'=>'2026-10-20 17:00'];
        $this->post(route('exams.schedule.store',$exam),['rows'=>[$key=>$schedule]])->assertSessionHasNoErrors();
        $this->post(route('exams.schedule.publish',$exam))->assertSessionHasNoErrors();
        $paper=$subject->schedules()->firstOrFail();
        $teacher=User::where('email','teacher@test.com')->firstOrFail();
        $this->actingAs($teacher)->get(route('teacher.exams.index'))->assertOk()->assertSee('Enter Marks')->assertSee('12 Oct 2026');
        $this->get(route('teacher.exams.marks.edit',$paper))->assertOk()->assertSee('Save Draft');
        $roster=$paper->fresh()->quiz->examRosterEntries()->get();
        $rows=$roster->map(fn($entry)=>['student_id'=>$entry->student_id,'attendance_status'=>'present','marks_obtained'=>null])->all();
        $rows[0]['marks_obtained']=0;
        $this->post(route('teacher.exams.marks.save',$paper),['rows'=>$rows,'action'=>'draft'])->assertSessionHasNoErrors();
        $this->assertEquals('0.00',$paper->fresh()->quiz->results()->first()->marks_obtained);
        $this->post(route('teacher.exams.marks.save',$paper),['rows'=>$rows,'action'=>'submit'])->assertSessionHasErrors();
        foreach ($rows as $i=>&$row) { $row['marks_obtained']=82; } unset($row);
        $rows[1]['attendance_status']='absent';$rows[1]['marks_obtained']=null;
        $this->post(route('teacher.exams.marks.save',$paper),['rows'=>$rows,'action'=>'submit'])->assertSessionHasNoErrors();
        $this->post(route('teacher.exams.marks.save',$paper),['rows'=>$rows,'action'=>'draft'])->assertSessionHasErrors();
        $this->actingAs($this->admin)->get(route('exams.review',$paper))->assertOk()->assertSee('Approve');
        $this->post(route('exams.review.action',$paper),['action'=>'return','reason'=>''])->assertSessionHasErrors();
        $this->post(route('exams.review.action',$paper),['action'=>'return','reason'=>'Verify marks'])->assertSessionHasNoErrors();
        $this->actingAs($teacher)->get(route('teacher.exams.marks.edit',$paper))->assertOk()->assertSee('Verify marks');
        $this->post(route('teacher.exams.marks.save',$paper),['rows'=>$rows,'action'=>'submit'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('exams.review.action',$paper),['action'=>'approve'])->assertSessionHasNoErrors();
        $this->assertSame('approved',$paper->fresh()->marks_status);
        $this->assertNull($exam->fresh()->result_published_at);
        $this->assertDatabaseHas('notifications',['notifiable_id'=>$teacher->id]);
        $this->assertDatabaseHas('exam_logs',['exam_id'=>$exam->id,'action'=>'marks_approve']);
    }

    public function test_schedule_validation_rejects_conflicts_holidays_and_invalid_dates(): void
    {
        $exam=$this->exam();
        $this->post(route('exams.subjects.store',$exam),$this->subjectData())->assertSessionHasNoErrors();
        $subject=$exam->subjects()->firstOrFail();$key=$subject->id.':'.$this->assignment->section_id;
        $row=['exam_date'=>'2026-10-12','start_time'=>'09:00','end_time'=>'11:00','room'=>'101','invigilator_id'=>$this->assignment->teacher_id,'marks_deadline'=>'2026-10-20 17:00'];
        foreach ([['exam_date'=>'2026-10-11'],['exam_date'=>'2026-10-18'],['end_time'=>'08:00'],['marks_deadline'=>'2026-10-12 08:00']] as $invalid) {
            $this->post(route('exams.schedule.store',$exam),['rows'=>[$key=>array_replace($row,$invalid)]])->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('exam_schedules',0);
        $this->post(route('exams.schedule.store',$exam),['rows'=>[$key=>$row]])->assertSessionHasNoErrors();
        $other=$this->exam();
        $this->post(route('exams.subjects.store',$other),$this->subjectData())->assertSessionHasNoErrors();
        $otherKey=$other->subjects()->first()->id.':'.$this->assignment->section_id;
        $this->post(route('exams.schedule.store',$other),['rows'=>[$otherKey=>$row]])->assertSessionHasErrors();
        $this->assertDatabaseCount('exam_schedules',1);
    }

    public function test_marks_block_other_teachers_invalid_marks_and_expired_deadlines(): void
    {
        $exam=$this->exam();$this->post(route('exams.subjects.store',$exam),$this->subjectData());
        $subject=$exam->subjects()->first();
        $paper=ExamSchedule::create(['exam_subject_id'=>$subject->id,'section_id'=>$this->assignment->section_id,'teacher_id'=>$this->assignment->teacher_id,
            'exam_date'=>'2026-10-12','start_time'=>'09:00','end_time'=>'11:00','paper_deadline'=>'2026-10-12','marks_deadline'=>now()->addDays(2)]);
        $exam->update(['datesheet_published_at'=>now(),'status'=>'schedule_published']);
        $outsider=User::factory()->create(['role'=>'Teacher']);
        $this->actingAs($outsider)->get(route('teacher.exams.marks.edit',$paper))->assertForbidden();
        $teacher=User::where('email','teacher@test.com')->firstOrFail();
        $this->actingAs($teacher)->get(route('teacher.exams.marks.edit',$paper))->assertOk();
        $id=$paper->fresh()->quiz->examRosterEntries()->first()->student_id;
        foreach ([-1,101,12.345] as $marks) $this->post(route('teacher.exams.marks.save',$paper),[
            'rows'=>[['student_id'=>$id,'attendance_status'=>'present','marks_obtained'=>$marks]],'action'=>'draft'])->assertSessionHasErrors();
        $paper->update(['marks_deadline'=>now()->subMinute()]);
        $this->post(route('teacher.exams.marks.save',$paper),['rows'=>[['student_id'=>$id,'attendance_status'=>'present','marks_obtained'=>82]],'action'=>'draft'])->assertSessionHasErrors();
        $this->actingAs($this->admin)->post(route('exams.deadline',$paper),['marks_deadline'=>now()->addDays(10)->format('Y-m-d H:i'),'reason'=>'Allow correction'])->assertSessionHasNoErrors();
        $this->actingAs($teacher)->post(route('teacher.exams.marks.save',$paper),['rows'=>[['student_id'=>$id,'attendance_status'=>'present','marks_obtained'=>82]],'action'=>'draft'])->assertSessionHasNoErrors();
    }
}

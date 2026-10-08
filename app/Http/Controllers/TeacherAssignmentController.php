<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignmentRequest;
use App\Http\Requests\ClassTeacherRequest;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TeacherAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['edit' => 'nullable|integer|exists:teacher_assignments,id']);
        view()->share('editing', $request->filled('edit') ? TeacherAssignment::findOrFail($request->edit) : null);
        $request->validate(['class_id'=>'nullable|integer|exists:school_classes,id','section_id'=>'nullable|integer|exists:school_sections,id',
            'subject_id'=>'nullable|integer|exists:subjects,id','session_id'=>'nullable|integer|exists:academic_sessions,id','exam_id'=>'nullable|integer|exists:exams,id']);
        return view('teachers.assignments', ['teachers' => Teacher::where('status', 'Active')->withCount('assignments')->withSum('assignments', 'weekly_periods')->orderBy('name')->get(), 'classes' => SchoolClass::orderBy('name')->get(), 'sections' => SchoolSection::orderBy('name')->get(), 'subjects' => Subject::where('status', 'Active')->orderBy('name')->get(), 'sessions' => AcademicSession::orderBy('name')->get(), 'assignments' => TeacherAssignment::with(['teacher', 'schoolClass', 'section', 'subject', 'session'])->latest()->paginate(20)]);
    }

    public function store(AssignmentRequest $request)
    {
        return $this->save($request);
    }

    public function update(AssignmentRequest $request, TeacherAssignment $assignment)
    {
        return $this->save($request, $assignment);
    }

    private function save(AssignmentRequest $request, ?TeacherAssignment $assignment = null)
    {
        DB::transaction(function () use ($request, $assignment) {
            $data = $request->validated();
            $subject = Subject::whereKey($data['subject_id'])->lockForUpdate()->first();
            if (! $subject || $subject->status !== 'Active') {
                throw ValidationException::withMessages(['subject_id' => 'Select an active subject.']);
            }
            // All writes for this session share one lock, including different teachers.
            AcademicSession::whereKey($data['session_id'])->lockForUpdate()->firstOrFail();
            $teacher = Teacher::whereKey($data['teacher_id'])->lockForUpdate()->firstOrFail();
            if ($teacher->status !== 'Active') {
                throw ValidationException::withMessages(['teacher_id' => 'Select an active teacher.']);
            }
            $group = TeacherAssignment::where('class_id', $data['class_id'])->where('section_id', $data['section_id'])->where('session_id', $data['session_id'])->when($assignment, fn ($q) => $q->whereKeyNot($assignment->id));
            if ((clone $group)->where('subject_id', $data['subject_id'])->exists()) {
                throw ValidationException::withMessages(['subject_id' => 'This subject already has a teacher in this class, section and session.']);
            }
            if ($request->boolean('is_class_teacher')) {
                if ((clone $group)->where('is_class_teacher', true)->where('teacher_id', '!=', $data['teacher_id'])->exists() || TeacherAssignment::where('teacher_id', $data['teacher_id'])->where('session_id', $data['session_id'])->where('is_class_teacher', true)->when($assignment, fn ($q) => $q->whereKeyNot($assignment->id))->where(fn ($q) => $q->where('class_id', '!=', $data['class_id'])->orWhere('section_id', '!=', $data['section_id']))->exists()) {
                    throw ValidationException::withMessages(['is_class_teacher' => 'A class teacher may lead only one class/section per session, and a class/section may have only one class teacher.']);
                }
                (clone $group)->where('teacher_id', $data['teacher_id'])->update(['is_class_teacher' => false]);
            }
            $data['is_class_teacher'] = $request->boolean('is_class_teacher');
            $assignment ? $assignment->update($data) : TeacherAssignment::create($data);
        }, 3);

        return $assignment
            ? redirect()->route('teachers.assignments.index')->with('success', 'Assignment updated.')
            : back()->with('success', 'Assignment added.');
    }

    public function destroy(TeacherAssignment $assignment)
    {
        $assignment->delete();

        return back()->with('success', 'Assignment removed.');
    }

    public function classTeacher(ClassTeacherRequest $request, TeacherAssignment $assignment)
    {
        DB::transaction(function () use ($request, $assignment) {
            AcademicSession::whereKey($assignment->session_id)->lockForUpdate()->firstOrFail();
            $assignment = TeacherAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $teacher = Teacher::whereKey($assignment->teacher_id)->lockForUpdate()->firstOrFail();
            if ($teacher->status !== 'Active') {
                throw ValidationException::withMessages(['teacher_id' => 'Class teachers must be active.']);
            }
            if ($request->boolean('is_class_teacher')) {
                $group = TeacherAssignment::where('class_id', $assignment->class_id)->where('section_id', $assignment->section_id)->where('session_id', $assignment->session_id);
                if ((clone $group)->where('is_class_teacher', true)->where('teacher_id', '!=', $assignment->teacher_id)->exists() || TeacherAssignment::where('teacher_id', $assignment->teacher_id)->where('session_id', $assignment->session_id)->where('is_class_teacher', true)->where(fn ($q) => $q->where('class_id', '!=', $assignment->class_id)->orWhere('section_id', '!=', $assignment->section_id))->exists()) {
                    throw ValidationException::withMessages(['is_class_teacher' => 'Clear the conflicting class teacher first.']);
                }
                (clone $group)->where('teacher_id', $assignment->teacher_id)->update(['is_class_teacher' => false]);
            }
            $assignment->update(['is_class_teacher' => $request->boolean('is_class_teacher')]);
        }, 3);

        return back()->with('success', 'Class teacher updated.');
    }
}

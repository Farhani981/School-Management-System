<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AcademicSetupController extends Controller
{
    private const TABLES = ['class' => 'school_classes', 'section' => 'school_sections', 'session' => 'academic_sessions'];

    public function editClass(SchoolClass $schoolClass)
    {
        return view('students.edit-class', compact('schoolClass'));
    }

    public function updateClass(Request $request, SchoolClass $schoolClass)
    {
        $request->merge(['name' => preg_replace('/\s+/u', ' ', trim((string) $request->input('name')))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('school_classes', 'name')->ignore($schoolClass->id)]]);
        $schoolClass->update($data);
        DashboardStatsService::invalidate();
        return redirect()->route('academic-setup.index')->with('success', 'Class updated successfully.');
    }

    public function destroyClass(SchoolClass $schoolClass)
    {
        DB::transaction(function () use ($schoolClass) {
            $schoolClass = SchoolClass::whereKey($schoolClass->id)->lockForUpdate()->firstOrFail();
            // Protect all class references, including legacy columns without foreign keys.
            foreach (Schema::getTableListing() as $table) {
                $classColumns = array_intersect(Schema::getColumnListing($table), ['class_id', 'school_class_id', 'applying_for_class_id', 'from_class_id', 'to_class_id']);
                foreach ($classColumns as $column) {
                    if (DB::table($table)->where($column, $schoolClass->id)->exists()) {
                        throw ValidationException::withMessages(['class' => 'This class has linked students or academic records and cannot be deleted. Move or remove those records first.']);
                    }
                }
            }
            $schoolClass->delete();
        });
        DashboardStatsService::invalidate();
        return redirect()->route('academic-setup.index')->with('success', 'Class deleted successfully.');
    }

    public function index()
    {
        $currentSession = AcademicSession::where('is_current', true)->value('id');
        $classStudents = Student::where('status', 'Active')
            ->when($currentSession, fn ($query) => $query->where('academic_session_id', $currentSession))
            ->selectRaw('class_id, COUNT(*) AS student_count, COUNT(DISTINCT section_id) AS section_count')
            ->groupBy('class_id')->get()->keyBy('class_id');
        $classTeachers = TeacherAssignment::query()
            ->when($currentSession, fn ($query) => $query->where('session_id', $currentSession))
            ->selectRaw('class_id, COUNT(DISTINCT teacher_id) AS teacher_count')
            ->groupBy('class_id')->pluck('teacher_count', 'class_id');
        $classSections = Student::where('status', 'Active')
            ->when($currentSession, fn ($query) => $query->where('academic_session_id', $currentSession))
            ->whereNotNull('section_id')->distinct()->get(['class_id', 'section_id'])
            ->concat(TeacherAssignment::query()
                ->when($currentSession, fn ($query) => $query->where('session_id', $currentSession))
                ->whereNotNull('section_id')->distinct()->get(['class_id', 'section_id']))
            ->unique(fn ($row) => $row->class_id.':'.$row->section_id)
            ->groupBy('class_id')->map(fn ($rows) => $rows->pluck('section_id'));

        return view('students.setup', compact('currentSession', 'classStudents', 'classTeachers', 'classSections') + ['options' => StudentRecordsController::options()]);
    }

    public function current(Request $request)
    {
        $data = $request->validate(['academic_session_id' => 'required|integer|exists:academic_sessions,id']);
        DB::transaction(function () use ($data) {
            AcademicSession::orderBy('id')->lockForUpdate()->get(['id']);
            DB::table('academic_sessions')->update(['is_current' => false]);
            AcademicSession::findOrFail($data['academic_session_id'])->update(['is_current' => true]);
        });

        return back()->with('success', 'Current academic session updated. Dashboard refreshed.');
    }

    public function store(Request $request)
    {
        $request->validate(['type' => ['required', Rule::in(array_keys(self::TABLES))]]);
        $table = self::TABLES[$request->input('type')];
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique($table, 'name')]]);
        $id = DB::table($table)->insertGetId(['name' => $data['name'], 'created_at' => now(), 'updated_at' => now()]);
        DashboardStatsService::invalidate();
        if ($request->expectsJson()) {
            return response()->json(['id' => $id, 'name' => $data['name']], 201);
        }

        return back()->with('success', 'Academic option added.');
    }
}

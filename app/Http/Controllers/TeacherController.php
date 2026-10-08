<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherActionRequest;
use App\Http\Requests\TeacherRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\LeaveType;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherDocument;
use App\Services\TeacherRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherController extends Controller
{
    public static function directory(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:255', 'status' => ['nullable', Rule::in(['Active', 'Inactive', 'On Leave'])], 'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])], 'department_id' => 'nullable|integer', 'designation_id' => 'nullable|integer', 'sort' => ['nullable', Rule::in(['name', 'employee_id', 'phone', 'status', 'joining_date'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]);
        $query = Teacher::with(['department', 'designation']);
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                foreach (['name', 'employee_id', 'phone', 'cnic'] as $field) {
                    $q->orWhere($field, 'like', '%'.$search.'%');
                }
            });
        }
        foreach (['status', 'department_id', 'designation_id', 'gender'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return $query->orderBy($request->input('sort', 'name'), $request->input('direction', 'asc'))->orderBy('id');
    }

    public static function options(): array
    {
        return ['departments' => Department::orderBy('name')->get(), 'designations' => Designation::orderBy('name')->get()];
    }

    public function index(Request $request)
    {
        return view('teachers.index', self::options() + ['teachers' => self::directory($request)->paginate(15)->withQueryString()]);
    }

    public function create()
    {
        return view('teachers.form', self::options() + ['teacher' => new Teacher, 'salary' => null]);
    }

    public function store(TeacherRequest $request, TeacherRecords $records)
    {
        $teacher = $records->save($request->validated());

        return redirect()->route('teachers.show', $teacher)->with('success', 'Teacher added.');
    }

    public function edit(Teacher $teacher)
    {
        $teacher->load(['salaryStructure', 'user', 'documents']);

        return view('teachers.form', self::options() + ['teacher' => $teacher, 'salary' => $teacher->salaryStructure]);
    }

    public function update(TeacherRequest $request, Teacher $teacher, TeacherRecords $records)
    {
        $records->save($request->validated(), $teacher);

        return redirect()->route('teachers.show', $teacher)->with('success', 'Teacher updated.');
    }

    public function show(Teacher $teacher)
    {
        $teacher->load(['department', 'designation', 'documents', 'assignments.schoolClass', 'assignments.section', 'assignments.subject', 'assignments.session', 'leaves.leaveType', 'salaryStructure', 'payrolls']);

        return view('teachers.show', compact('teacher'));
    }

    public function destroy(Teacher $teacher)
    {
        $teacher->delete();

        return redirect()->route('teachers.index')->with('success', 'Teacher archived; payroll and documents preserved.');
    }

    public function document(Teacher $teacher, TeacherDocument $document)
    {
        abort_unless($document->teacher_id === $teacher->id, 404);

        return Storage::disk('local')->download($document->path, $document->name);
    }

    public function deleteDocument(Teacher $teacher, TeacherDocument $document)
    {
        abort_unless($document->teacher_id === $teacher->id, 404);
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }

    public function setup(TeacherActionRequest $request)
    {
        $data = $request->validated();
        $model = ['department' => Department::class, 'designation' => Designation::class, 'subject' => Subject::class, 'leave_type' => LeaveType::class][$data['type']];
        $attributes = ['name' => $data['name']];
        if ($data['type'] === 'leave_type') {
            $attributes += ['annual_days' => $data['annual_days'], 'is_paid' => $request->boolean('is_paid')];
        }
        if ($data['type'] === 'subject') {
            $existing = Subject::withTrashed()->where('name', $data['name'])->first();
            if ($existing && ($existing->trashed() || $existing->status === 'Inactive')) {
                throw ValidationException::withMessages(['name' => 'This subject is inactive or archived. Manage it in the Subjects module.']);
            }
        }
        $option = $model::firstOrCreate(['name' => $data['name']], $attributes);

        if ($request->expectsJson()) {
            return response()->json(['id' => $option->id, 'name' => $option->name], $option->wasRecentlyCreated ? 201 : 200);
        }

        return back()->with('success', 'Setup option saved.');
    }
}

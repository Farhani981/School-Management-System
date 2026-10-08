<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Policies\AttendancePolicy;
use App\Services\AttendanceReports;
use App\Services\FeeCalculatorService;
use App\Services\StudentDirectory;
use App\Services\StudentRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class StudentController extends Controller
{
    public function index(Request $request, StudentDirectory $directory)
    {
        $filters = $directory->filters($request);
        $query = $directory->query($filters);

        $options = StudentRecordsController::options();

        return view('students.index', [
            'students' => $query->paginate((int) ($filters['per_page'] ?? 10))->withQueryString(),
            'classes' => $options['class_id'],
            'sections' => $options['section_id'],
            'options' => $options,
            'filters' => $filters,
            'directoryCounts' => $directory->counts($filters),
        ]);
    }

    public function create()
    {
        return view('students.create', ['student' => new Student, 'options' => StudentRecordsController::options()]);
    }

    public function store(StoreStudentRequest $request)
    {
        $student = app(StudentRecords::class)->save(new Student, $request->safe()->except(['admission_no', 'photo', 'documents']), $request->file('documents', []), $request->file('photo'));

        return redirect()->route('students.show', $student)->with('success', 'Student added successfully.');
    }

    public function show(Student $student, Request $request)
    {
        Gate::authorize('view', $student);

        $request->validate(['attendance_month' => 'nullable|date_format:Y-m']);

        // Eager load relations to prevent N+1 queries
        $student->load(['schoolClass', 'section', 'academicSession', 'documents', 'user']);

        $canViewAttendance = $request->user() && (AttendancePolicy::admin($request->user()) || ($student->class_id && $student->section_id && $student->academic_session_id && Gate::allows('mark', [SchoolClass::findOrFail($student->class_id), $student->section_id, $student->academic_session_id])));
        $attendanceProfile = $canViewAttendance ? app(AttendanceReports::class)->profile($student, $request->input('attendance_month', now()->format('Y-m'))) : null;

        $canViewFees = Gate::allows('viewFees', $student);
        $canViewDocuments = Gate::allows('viewDocuments', $student);

        $feeData = $canViewFees ? $this->feeData($student) : null;
        $documents = $canViewDocuments ? $student->documents()->latest('id')->get() : collect();
        $siblings = $student->siblings()->with(['schoolClass', 'section'])->get();

        return view('students.show', [
            'student' => $student,
            'feeData' => $feeData,
            'attendanceProfile' => $attendanceProfile,
            'siblings' => $siblings,
            'options' => StudentRecordsController::options(),
            'documents' => $documents,
            'histories' => $student->histories()->latest('id')->paginate(15),
            'canViewFees' => $canViewFees,
            'canViewDocuments' => $canViewDocuments,
        ]);
    }

    public function changeStatus(Student $student, Request $request)
    {
        Gate::authorize('updateStatus', $student);

        $validated = $request->validate([
            'status' => ['required', 'string', \Illuminate\Validation\Rule::in(\App\Models\Student::STATUSES)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldStatus = $student->status;
        $newStatus = $validated['status'];
        $reason = $validated['reason'] ?: 'Status updated to '.$newStatus;

        $student->status = $newStatus;
        $student->save();

        $student->histories()->create([
            'event' => 'Status changed to '.$newStatus,
            'notes' => 'From: '.$oldStatus.' | Reason: '.$reason,
        ]);

        return back()->with('success', "Student status successfully changed to {$newStatus}.");
    }

    public function managePortalLogin(Student $student, Request $request)
    {
        Gate::authorize('manageLogin', $student);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($student->user_id)],
            'password' => [$student->user_id ? 'nullable' : 'required', 'string', 'min:10'],
        ]);

        $user = $student->user ?? new \App\Models\User();
        $user->name = $student->full_name ?: ($student->first_name . ' ' . $student->last_name);
        $user->email = $validated['email'];
        $user->role = 'Student';
        if (! empty($validated['password'])) {
            $user->password = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }
        $user->save();

        $student->user_id = $user->id;
        $student->email = $user->email;
        $student->save();

        return back()->with('success', 'Student portal account updated successfully.');
    }

    public function edit(Student $student)
    {
        return view('students.edit', ['student' => $student, 'feeData' => $this->feeData($student), 'options' => StudentRecordsController::options()]);
    }

    public function update(UpdateStudentRequest $request, Student $student)
    {
        $student = app(StudentRecords::class)->save($student, $request->safe()->except(['photo', 'documents']), $request->file('documents', []), $request->file('photo'));

        return redirect()->route('students.show', $student)->with('success', 'Student updated successfully.');
    }

    public function destroy(Student $student)
    {
        $student->delete();

        return redirect()->route('students.index')->with('success', 'Student deleted successfully.');
    }

    private function feeData(Student $student): ?array
    {
        if (! Gate::allows('fees.manage') || ! Schema::hasTable('fee_types')) {
            return null;
        }
        $student->load('feeOverrides.feeType');

        return ['types' => FeeType::active()->orderBy('name')->get(), 'overrides' => $student->feeOverrides, 'calculation' => app(FeeCalculatorService::class)->calculateForStudent($student, (int) now()->month, (int) now()->year),
            'vouchers' => Schema::hasTable('fee_vouchers') ? \App\Models\FeeVoucher::with('payments')->where('student_id', $student->id)->orderByDesc('id')->get() : collect()];
    }
}

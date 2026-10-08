<?php

namespace App\Http\Controllers;

use App\Exports\AdmissionRegisterExport;
use App\Http\Requests\AdmissionRegisterRequest;
use App\Http\Requests\ApproveAdmissionRequest;
use App\Http\Requests\StoreAdmissionApplicationRequest;
use App\Models\AdmissionApplication;
use App\Models\Student;
use App\Services\AdmissionRegister;
use App\Services\StudentRecords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class AdmissionsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])], 'class_id' => ['nullable', 'integer', 'exists:school_classes,id']]);
        $query = AdmissionApplication::query();
        if (!empty($filters['search'])) $query->where(fn ($q) => $q->where('applicant_name', 'like', '%'.$filters['search'].'%')->orWhere('father_name', 'like', '%'.$filters['search'].'%')->orWhere('phone', 'like', '%'.$filters['search'].'%'));
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['class_id'])) $query->where('applying_for_class_id', $filters['class_id']);
        return view('admissions.index', ['applications' => $query->latest('id')->paginate(15)->withQueryString(), 'options' => StudentRecordsController::options(), 'filters' => $filters]);
    }

    public function store(StoreAdmissionApplicationRequest $request)
    {
        AdmissionApplication::create($request->validated());
        return redirect()->route('admissions.index')->with('success', 'Admission application added.');
    }

    public function create()
    {
        return view('students.create', ['student' => new Student, 'options' => StudentRecordsController::options()]);
    }

    public function review(AdmissionApplication $application)
    {
        abort_unless($application->status === 'pending', 409, 'This application has already been reviewed.');
        $names = preg_split('/\s+/', trim($application->applicant_name), 2);
        $student = new Student(['first_name' => $names[0], 'last_name' => $names[1] ?? '', 'father_name' => $application->father_name, 'phone' => $application->phone, 'class_id' => $application->applying_for_class_id, 'status' => 'Active']);
        return view('admissions.review', ['application' => $application, 'student' => $student, 'options' => StudentRecordsController::options()]);
    }

    public function approve(ApproveAdmissionRequest $request, AdmissionApplication $application, StudentRecords $records)
    {
        $student = DB::transaction(function () use ($request, $application, $records) {
            $application = AdmissionApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($application->status !== 'pending') throw ValidationException::withMessages(['application' => 'This application has already been reviewed.']);
            $student = $records->save(new Student, $request->safe()->except(['admission_no', 'photo', 'documents']), $request->file('documents', []), $request->file('photo'));
            $application->update(['status' => 'approved', 'reviewed_by' => $request->user()?->id, 'reviewed_at' => now(), 'student_id' => $student->id]);
            $student->histories()->create(['event' => 'Application approved', 'notes' => 'Admission application #'.$application->id.($application->previous_school ? '; previous school: '.$application->previous_school : '')]);
            return $student;
        });
        return redirect()->route('students.show', $student)->with('success', 'Application approved and student admitted.');
    }

    public function reject(Request $request, AdmissionApplication $application)
    {
        DB::transaction(function () use ($request, $application) {
            $application = AdmissionApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($application->status !== 'pending') throw ValidationException::withMessages(['application' => 'This application has already been reviewed.']);
            $application->update(['status' => 'rejected', 'reviewed_by' => $request->user()?->id, 'reviewed_at' => now()]);
        });
        return back()->with('success', 'Application rejected.');
    }

    public function register(AdmissionRegisterRequest $request, AdmissionRegister $register)
    {
        $filters = $request->validated();
        return view('admissions.register', ['students' => $register->query($filters)->paginate(25)->withQueryString(), 'filters' => $filters, 'options' => StudentRecordsController::options()]);
    }

    public function export(AdmissionRegisterRequest $request, string $format, AdmissionRegister $register)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf']), 404);
        $filters = $request->validated();
        $options = StudentRecordsController::options();
        if ($format === 'xlsx') return Excel::download(new AdmissionRegisterExport($filters, $options), 'admission-register.xlsx');
        return Pdf::loadView('admissions.pdf.register', ['students' => $register->query($filters)->get(), 'options' => $options])->setPaper('a4', 'landscape')->download('admission-register.pdf');
    }
}

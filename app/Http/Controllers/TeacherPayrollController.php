<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollRequest;
use App\Http\Requests\TeacherActionRequest;
use App\Models\Payroll;
use App\Models\StaffAttendance;
use App\Models\Teacher;
use App\Models\TeacherAdvance;
use App\Models\TeacherAttendance;
use App\Services\TeacherLeaves;
use App\Services\TeacherPayroll;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TeacherPayrollController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['month' => 'nullable|date_format:Y-m']);
        $month = Carbon::createFromFormat('!Y-m', $request->input('month', now()->format('Y-m')));
        $query = Payroll::with('teacher')->whereDate('month', $month);

        return view('teachers.payroll', ['month' => $month, 'teachers' => Teacher::orderBy('name')->get(), 'payrolls' => $query->get(), 'advances' => TeacherAdvance::with('teacher')->whereDate('month', $month)->get(), 'attendances' => TeacherAttendance::with('teacher')->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->orderByDesc('date')->paginate(15)->withQueryString()]);
    }

    public function generate(PayrollRequest $request, TeacherPayroll $service)
    {
        $count = $service->generate($request->validated('month'));

        return back()->with('success', "Generated {$count} payrolls. Existing payrolls were preserved.");
    }

    public function paid(Payroll $payroll)
    {
        DB::transaction(function () use ($payroll) {
            $payroll = Payroll::whereKey($payroll->id)->lockForUpdate()->firstOrFail();
            if ($payroll->status !== 'Paid') {
                $payroll->update(['status' => 'Paid', 'paid_at' => now()]);
            }
        });

        return back()->with('success', 'Payroll marked paid.');
    }

    public function slip(Payroll $payroll)
    {
        $payroll->load('teacher.designation', 'teacher.department', 'teacher.salaryStructure');

        return Pdf::loadView('teachers.pdf.slip', compact('payroll'))->download('salary-'.$payroll->teacher->employee_id.'-'.$payroll->month->format('Y-m').'.pdf');
    }

    public function report(PayrollRequest $request)
    {
        $month = Carbon::createFromFormat('!Y-m', $request->validated('month'));
        $payrolls = Payroll::with('teacher')->whereDate('month', $month)->get();

        return Pdf::loadView('teachers.pdf.report', compact('month', 'payrolls'))->setPaper('a4', 'landscape')->download('payroll-'.$month->format('Y-m').'.pdf');
    }

    public function advance(TeacherActionRequest $request, TeacherLeaves $leaves)
    {
        DB::transaction(function () use ($request, $leaves) {
            $data = $request->validated();
            $teacher = Teacher::whereKey($data['teacher_id'])->lockForUpdate()->firstOrFail();
            $data['month'] .= '-01';
            $leaves->assertUnprocessed($teacher->id, $data['month'], $data['month']);
            $teacher->advances()->create($data);
        }, 3);

        return back()->with('success', 'Advance recorded.');
    }

    public function attendance(TeacherActionRequest $request, TeacherLeaves $leaves)
    {
        Gate::authorize('attendance.staff');
        DB::transaction(function () use ($request, $leaves) {
            $data = $request->validated();
            $teacher = Teacher::whereKey($data['teacher_id'])->lockForUpdate()->firstOrFail();
            $data['date'] = Carbon::parse($data['date'])->toDateString();
            $leaves->assertUnprocessed($teacher->id, $data['date'], $data['date']);
            $teacher->attendances()->updateOrCreate(['date' => $data['date']], ['status' => $data['status']]);
            StaffAttendance::updateOrCreate(['teacher_id' => $teacher->id, 'date' => $data['date']], ['status' => strtolower($data['status']), 'late_minutes' => 0]);
        }, 3);

        return back()->with('success', 'Attendance saved.');
    }
}

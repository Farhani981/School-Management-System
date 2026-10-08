<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffAttendanceRequest;
use App\Http\Requests\StudentAttendanceBatchRequest;
use App\Models\SchoolClass;
use App\Models\StaffAttendance;
use App\Models\StudentAttendance;
use App\Services\AttendanceRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AttendanceController extends Controller
{
    public function students(Request $request, AttendanceRecords $service)
    {
        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d|before_or_equal:today', 'school_class_id' => 'nullable|integer|exists:school_classes,id', 'section_id' => 'nullable|integer|exists:school_sections,id', 'academic_session_id' => 'nullable|integer|exists:academic_sessions,id']);
        $data['date'] ??= now()->toDateString();
        $students = collect();
        $marked = collect();
        if (! empty($data['school_class_id']) && ! empty($data['section_id']) && ! empty($data['academic_session_id'])) {
            Gate::authorize('mark', [SchoolClass::findOrFail($data['school_class_id']), $data['section_id'], $data['academic_session_id'], $data['date']]);
            $students = $service->roster($data)->get();
            $marked = StudentAttendance::whereIn('student_id', $students->pluck('id'))->whereDate('date', $data['date'])->get()->keyBy('student_id');
        }

        return view('attendance.students', $service->options($request->user(), $data['date']) + compact('data', 'students', 'marked') + ['warnings' => $service->warnings($data['date'])]);
    }

    public function saveStudents(StudentAttendanceBatchRequest $request, AttendanceRecords $service)
    {
        $data = $request->validated();
        $service->students($data, $request->user());

        return redirect()->route('attendance.students.index', collect($data)->only(['date', 'school_class_id', 'section_id', 'academic_session_id'])->all())->with('success', 'Student attendance saved. Existing records updated.');
    }

    public function staff(Request $request, AttendanceRecords $service)
    {
        Gate::authorize('attendance.staff');
        $request->validate(['date' => 'nullable|date_format:Y-m-d|before_or_equal:today']);
        $date = $request->input('date', now()->toDateString());

        return view('attendance.staff', ['date' => $date, 'teachers' => $service->staffRoster($date)->get(), 'marked' => StaffAttendance::whereDate('date', $date)->get()->keyBy('teacher_id'), 'warnings' => $service->warnings($date)]);
    }

    public function saveStaff(StaffAttendanceRequest $request, AttendanceRecords $service)
    {
        Gate::authorize('attendance.staff');
        $service->staff($request->validated());

        return redirect()->route('attendance.staff.index', ['date' => $request->date])->with('success','Staff attendance saved and payroll attendance updated.');
    }
}

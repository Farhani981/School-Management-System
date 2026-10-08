<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherAttendanceRequest;
use App\Models\StudentAttendance;
use App\Models\TeacherAssignment;
use App\Services\AttendanceRecords;
use App\Services\TeacherPanelService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class AttendanceController extends Controller
{
    public function index(Request $request, TeacherPanelService $panel)
    {
        return view('teacher.attendance.index', ['cards' => array_values(array_filter($panel->cards($request->user()), fn ($card) => $card['can_mark_attendance']))]);
    }

    public function show(Request $request, TeacherAssignment $assignment, TeacherPanelService $panel, AttendanceRecords $records)
    {
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']])['date'] ?? today()->toDateString();
        Gate::authorize('mark', [$assignment, $date]);
        $assignment->load(['schoolClass', 'section', 'subject', 'session']);
        $students = $panel->roster($assignment)->get();
        $marked = StudentAttendance::whereIn('student_id', $students->modelKeys())->where('school_class_id', $assignment->class_id)
            ->where('section_id', $assignment->section_id)->where('academic_session_id', $assignment->session_id)->whereDate('date', $date)->get()->keyBy('student_id');
        return view('teacher.attendance.mark', compact('assignment', 'date', 'students', 'marked') + ['warnings' => $records->warnings($date)]);
    }

    public function store(TeacherAttendanceRequest $request, TeacherAssignment $assignment, AttendanceRecords $records)
    {
        $data = $request->validated() + ['school_class_id' => $assignment->class_id, 'section_id' => $assignment->section_id, 'academic_session_id' => $assignment->session_id];
        // Class/section/session come exclusively from the authorized assignment, never submitted IDs.
        $records->students($data, $request->user());
        Cache::forget(TeacherPanelService::cacheKey($request->user()));
        if ($request->expectsJson()) {
            $rows = StudentAttendance::whereIn('student_id', collect($data['rows'])->pluck('student_id'))
                ->where('school_class_id', $assignment->class_id)->where('section_id', $assignment->section_id)
                ->where('academic_session_id', $assignment->session_id)->whereDate('date', $data['date'])->get();
            $dashboard = app(TeacherPanelService::class)->dashboard($request->user());
            return response()->json(['marked' => true, 'present' => $rows->where('status', 'present')->count(),
                'total' => $rows->count(), 'dashboard' => $dashboard['pendingAttendance'], 'lowAttendanceCount' => $dashboard['lowAttendanceCount']]);
        }
        return redirect()->route('teacher.attendance.show', ['assignment' => $assignment, 'date' => $data['date']])->with('success', 'Attendance saved successfully.');
    }

    public function register(Request $request, TeacherAssignment $assignment, TeacherPanelService $panel)
    {
        Gate::authorize('view', $assignment);
        $month = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? today()->format('Y-m');
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);
        $students = $panel->roster($assignment)->paginate(30);
        $attendance = StudentAttendance::whereIn('student_id', $students->getCollection()->modelKeys())
            ->where('school_class_id', $assignment->class_id)->where('section_id', $assignment->section_id)
            ->where('academic_session_id', $assignment->session_id)->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->get()->groupBy('student_id');
        $assignment->load(['schoolClass', 'section', 'subject', 'session']);
        return view('teacher.attendance.register', compact('assignment', 'month', 'start', 'students', 'attendance'));
    }
}

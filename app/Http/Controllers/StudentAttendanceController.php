<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StudentAttendanceController extends Controller
{
    public function index(Request $request, Student $student)
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }
        abort_unless($student->class_id && $student->section_id && $student->academic_session_id, 422, 'Assign this student to a class, section and session first.');
        Gate::authorize('mark', [SchoolClass::findOrFail($student->class_id), $student->section_id, $student->academic_session_id]);

        return redirect()->route('attendance.students.index', ['date' => now()->toDateString(), 'school_class_id' => $student->class_id, 'section_id' => $student->section_id, 'academic_session_id' => $student->academic_session_id]);
    }

    public function store(Request $request, Student $student)
    {
        $response = $this->index($request, $student);

        return $response->withErrors(['attendance' => 'Use the class attendance screen to save the complete roster.']);
    }
}

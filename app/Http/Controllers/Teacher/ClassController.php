<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TeacherAssignment;
use App\Services\TeacherPanelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClassController extends Controller
{
    public function index(Request $request, TeacherPanelService $panel)
    {
        return view('teacher.classes.index', ['cards' => $panel->cards($request->user())]);
    }

    public function students(TeacherAssignment $assignment, TeacherPanelService $panel)
    {
        Gate::authorize('view', $assignment);
        $assignment->load(['schoolClass', 'section', 'subject', 'session']);
        return view('teacher.classes.students', ['assignment' => $assignment, 'students' => $panel->roster($assignment)->with('section')->paginate(30)]);
    }
}

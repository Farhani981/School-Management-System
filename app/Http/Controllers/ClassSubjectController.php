<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassSubjectsRequest;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\ClassSubjects;
use Illuminate\Http\Request;

class ClassSubjectController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['school_class_id' => 'nullable|exists:school_classes,id']);
        $classes = SchoolClass::orderBy('id')->get();
        $class = $request->filled('school_class_id') ? SchoolClass::with('subjects')->findOrFail($request->school_class_id) : null;

        return view('subjects.classes', ['classes' => $classes, 'selectedClass' => $class, 'subjects' => Subject::where('status', 'Active')->orderBy('name')->get(), 'assigned' => $class?->subjects->keyBy('id') ?? collect()]);
    }

    public function store(ClassSubjectsRequest $request, ClassSubjects $service)
    {
        $data = $request->validated();
        $service->save($data['school_class_id'], $data['subjects'] ?? []);

        return redirect()->route('subjects.classes.index', ['school_class_id' => $data['school_class_id']])->with('success', 'Class subjects saved.');
    }

    public function copy(ClassSubjectsRequest $request, ClassSubjects $service)
    {
        $data = $request->validated();
        $service->copy($data['school_class_id'], $data['source_class_id']);

        return redirect()->route('subjects.classes.index', ['school_class_id' => $data['school_class_id']])->with('success', 'Active subjects and marks settings copied.');
    }
}

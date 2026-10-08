<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentDirectory;
use App\Services\StudentPromotion;
use App\Services\StudentRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentBulkController extends Controller
{
    public function promotion(Request $request, StudentDirectory $directory)
    {
        $filters = $directory->filters($request);
        return view('students.promotion', ['students' => $directory->query($filters)->where('status', 'Active')->paginate(50)->withQueryString(), 'options' => StudentRecordsController::options(), 'filters' => $filters]);
    }

    public function store(Request $request, StudentPromotion $promotion, StudentRecords $records)
    {
        $data = $request->validate([
            'students' => ['required', 'array', 'min:1', 'max:100'],
            'students.*' => ['required', 'integer', 'distinct', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'action' => ['required', Rule::in(['status', 'delete', 'promote'])],
            'status' => ['required_if:action,status', 'nullable', Rule::in(Student::STATUSES)],
            'class_id' => ['required_if:action,promote', 'nullable', 'exists:school_classes,id'],
            'section_id' => ['required_if:action,promote', 'nullable', 'exists:school_sections,id'],
            'academic_session_id' => ['required_if:action,promote', 'nullable', 'exists:academic_sessions,id'],
            'first_roll' => ['required_if:action,promote', 'nullable', 'integer', 'min:1', 'max:999999'],
            'current_class_id' => ['nullable', 'integer'],
            'current_section_id' => ['nullable', 'integer'],
            'current_academic_session_id' => ['nullable', 'integer'],
        ]);
        DB::transaction(function () use ($data, $promotion, $records) {
            if ($data['action'] === 'promote') DB::table('school_classes')->where('id', $data['class_id'])->lockForUpdate()->first();
            $students = Student::whereIn('id', $data['students'])->orderBy('id')->lockForUpdate()->get();
            if ($students->count() !== count($data['students'])) throw ValidationException::withMessages(['students' => 'The selection changed. Reload and select students again.']);
            $roll = (int) ($data['first_roll'] ?? 1);
            foreach ($students as $student) {
                if ($data['action'] === 'delete') { $student->delete(); continue; }
                if ($data['action'] === 'status') { $records->save($student, ['status' => $data['status']]); continue; }
                foreach (['class_id', 'section_id', 'academic_session_id'] as $field) {
                    if (!empty($data['current_'.$field]) && (string) $student->$field !== (string) $data['current_'.$field]) {
                        throw ValidationException::withMessages(['students' => 'A selected student no longer belongs to the current assignment.']);
                    }
                }
                $promotion->promote($student, array_merge($data, ['roll_number' => (string) $roll++]));
            }
        }, 5);
        return back()->with('success', count($data['students']).' students updated successfully.');
    }
}

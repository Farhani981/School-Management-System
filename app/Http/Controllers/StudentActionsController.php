<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentBulkActionRequest;
use App\Models\Student;
use App\Services\StudentIdCards;
use App\Services\StudentRecords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentActionsController extends Controller
{
    public function bulk(StudentBulkActionRequest $request, StudentIdCards $cards, StudentRecords $records)
    {
        $data = $request->validated();
        if ($data['action'] === 'id_cards') {
            $students = Student::whereIn('id', $data['students'])->orderBy('id')->get();
            if ($students->count() !== count($data['students'])) throw ValidationException::withMessages(['students' => 'Selection changed. Reload students before printing.']);
            return $cards->download($students, 'student-id-cards.pdf');
        }
        DB::transaction(function () use ($data, $records) {
            $students = Student::whereIn('id', $data['students'])->orderBy('id')->lockForUpdate()->get();
            if ($students->count() !== count($data['students'])) throw ValidationException::withMessages(['students' => 'Selection changed. Reload students before continuing.']);
            foreach ($students as $student) $records->save($student, ['status' => $data['status']]);
        });
        return back()->with('success', count($data['students']).' student statuses updated.');
    }

    public function certificate(Student $student, string $type)
    {
        $titles = ['leaving' => 'School Leaving Certificate', 'character' => 'Character Certificate', 'bonafide' => 'Bonafide Certificate'];
        abort_unless(isset($titles[$type]), 404);
        return Pdf::loadView('students.pdf.certificate', ['student' => $student, 'type' => $type, 'title' => $titles[$type], 'options' => StudentRecordsController::options()])->setPaper('a4')->download($student->admission_no.'-'.$type.'.pdf');
    }
}

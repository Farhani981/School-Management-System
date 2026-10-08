<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentFeeOverrideRequest;
use App\Models\FeeType;
use App\Models\Student;
use App\Services\FeeSetupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentFeeOverrideController extends Controller
{
    public function store(StudentFeeOverrideRequest $request, Student $student)
    {
        DB::transaction(function () use ($request, $student) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            if (! FeeType::active()->whereKey($data['fee_type_id'])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['fee_type_id' => 'Select an active fee type.']);
            }
            $student->feeOverrides()->updateOrCreate(['fee_type_id' => $data['fee_type_id']], ['amount' => $data['amount'], 'reason' => $data['reason']]);
            FeeSetupService::log('Fee override saved for student #'.$student->id.' / fee type #'.$data['fee_type_id'].'.', 'fee_setup', 'students:'.$student->id);
        });

        return back()->withFragment('fees')->with('success', 'Special fee amount saved.');
    }

    public function destroy(Student $student, int $override)
    {
        DB::transaction(function () use ($student, $override) {
            $student->feeOverrides()->findOrFail($override)->delete();
            FeeSetupService::log('Fee override removed for student #'.$student->id.'.', 'fee_setup', 'students:'.$student->id);
        });

        return back()->withFragment('fees')->with('success', 'Special fee amount removed.');
    }
}

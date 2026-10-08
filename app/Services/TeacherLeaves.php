<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherLeaves
{
    public function balance(int $teacher, LeaveType $type, int $year): int
    {
        $used = LeaveRequest::where('teacher_id', $teacher)->where('leave_type_id', $type->id)->whereYear('start_date', $year)->whereIn('status', ['Pending', 'Approved'])->get()->sum(fn ($leave) => $leave->start_date->diffInDays($leave->end_date) + 1);

        return max(0, $type->annual_days - $used);
    }

    public function apply(array $data): void
    {
        DB::transaction(function () use ($data) {
            // Lock the parent to serialize balance and overlap checks per teacher.
            Teacher::whereKey($data['teacher_id'])->lockForUpdate()->firstOrFail();
            $type = LeaveType::findOrFail($data['leave_type_id']);
            $days = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;
            if ($days > $this->balance($data['teacher_id'], $type, Carbon::parse($data['start_date'])->year)) {
                throw ValidationException::withMessages(['end_date' => 'Insufficient leave balance (pending requests also reserve days).']);
            }
            if (LeaveRequest::where('teacher_id', $data['teacher_id'])->whereIn('status', ['Pending', 'Approved'])->whereDate('start_date', '<=', $data['end_date'])->whereDate('end_date', '>=', $data['start_date'])->exists()) {
                throw ValidationException::withMessages(['start_date' => 'This request overlaps an existing leave.']);
            }
            $this->assertUnprocessed($data['teacher_id'], $data['start_date'], $data['end_date']);
            LeaveRequest::create($data);
        }, 3);
    }

    public function assertUnprocessed(int $teacher, string $start, string $end): void
    {
        if (DB::table('payrolls')->where('teacher_id', $teacher)->whereDate('month', '>=', Carbon::parse($start)->startOfMonth()->toDateString())->whereDate('month', '<=', Carbon::parse($end)->startOfMonth()->toDateString())->exists()) {
            throw ValidationException::withMessages(['month' => 'Payroll already exists for this period. Changes to its attendance, leaves or advances are locked.']);
        }
    }
}

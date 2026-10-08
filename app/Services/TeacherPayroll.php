<?php

namespace App\Services;

use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherPayroll
{
    public function generate(string $month): int
    {
        return DB::transaction(function () use ($month) {
            $start = CarbonImmutable::createFromFormat('!Y-m', $month);
            $end = $start->copy()->endOfMonth();
            $count = 0;
            $teachers = Teacher::where('status', '!=', 'Inactive')->whereDate('joining_date', '<=', $end)->with(['salaryStructure', 'attendances' => fn ($q) => $q->whereBetween('date', [$start->toDateString(), $end->toDateString()]), 'leaves' => fn ($q) => $q->with('leaveType')->where('status', 'Approved')->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start), 'advances' => fn ($q) => $q->whereDate('month', $start)])->orderBy('id')->lockForUpdate()->get();
            foreach ($teachers as $teacher) {
                if ($teacher->payrolls()->whereDate('month', $start)->exists()) {
                    continue;
                }
                if ($teacher->leaves()->where('status', 'Pending')->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->exists()) {
                    throw ValidationException::withMessages(['month' => "Review pending leave requests for {$teacher->name} before generating payroll."]);
                }
                $salary = $teacher->salaryStructure;
                if (! $salary) {
                    throw ValidationException::withMessages(['month' => "Set a salary structure for {$teacher->name} before generating payroll."]);
                }
                $absences = [];
                foreach ($teacher->attendances as $attendance) {
                    if ($attendance->date >= $teacher->joining_date->toDateString() && in_array($attendance->status, ['Absent', 'Leave', 'Half Day'])) {
                        $absences[$attendance->date] = $attendance->status === 'Half Day' ? 0.5 : 1;
                    }
                }
                foreach ($teacher->leaves->where('leaveType.is_paid', false) as $leave) {
                    for ($day = CarbonImmutable::parse($leave->start_date)->max($start)->max(CarbonImmutable::parse($teacher->joining_date)); $day->lte(CarbonImmutable::parse($leave->end_date)->min($end)); $day = $day->addDay()) {
                        $absences[$day->toDateString()] = 1;
                    }
                }
                foreach ($teacher->leaves->where('leaveType.is_paid', true) as $leave) {
                    for ($day = CarbonImmutable::parse($leave->start_date)->max($start); $day->lte(CarbonImmutable::parse($leave->end_date)->min($end)); $day = $day->addDay()) {
                        unset($absences[$day->toDateString()]);
                    }
                }
                $basic = (int) round($salary->basic_salary * 100);
                $allowances = (int) round($salary->total_allowances * 100);
                $deductions = (int) round($salary->fixed_deductions * 100);
                $eligible = $start->daysInMonth - max(0, (int) $start->diffInDays($teacher->joining_date->copy()->max($start)));
                $basic = (int) round($basic * $eligible / $start->daysInMonth);
                $allowances = (int) round($allowances * $eligible / $start->daysInMonth);
                $absence = (int) round($salary->basic_salary * 100 / $start->daysInMonth * array_sum($absences));
                $advances = (int) round($teacher->advances->sum('amount') * 100);
                $net = $basic + $allowances - $deductions - $absence - $advances;
                if ($net < 0) {
                    throw ValidationException::withMessages(['month' => "Deductions exceed earnings for {$teacher->name}. Adjust advances or salary first."]);
                }
                $teacher->payrolls()->create(['month' => $start->toDateString(), 'basic_salary' => $basic / 100, 'allowances' => $allowances / 100, 'deductions' => $deductions / 100, 'absence_deduction' => $absence / 100, 'advances' => $advances / 100, 'net_salary' => $net / 100, 'unpaid_days' => array_sum($absences)]);
                $count++;
            }

            return $count;
        }, 3);
    }
}

<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAttendance;

class AttendanceStats
{
    public function today(): array
    {
        $total = Student::where('status', 'Active')->count();
        $counts = StudentAttendance::whereDate('date', now()->toDateString())->whereHas('student', fn ($q) => $q->where('status', 'Active')->whereNull('deleted_at'))->get()->countBy('status')->all();
        $marked = array_sum($counts);
        $counts = array_replace(array_fill_keys(StudentAttendance::STATUSES, 0), $counts);

        $eligible=$marked-$counts['leave'];
        return ['total' => $total, 'marked' => $marked, 'unmarked' => max(0, $total - $marked), 'counts' => $counts, 'percentage' => $eligible ? round(($counts['present'] + $counts['late'] + $counts['half_day'] / 2) / $eligible * 100, 1) : 0];
    }
}

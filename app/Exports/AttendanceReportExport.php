<?php

namespace App\Exports;

use App\Services\AttendanceReports;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class AttendanceReportExport implements WithMultipleSheets
{
    public function __construct(private array $report) {}

    public function sheets(): array
    {
        $r = $this->report;
        $data = [];
        $data['Daily'] = [['Date', 'Class', 'Section', 'Session', 'Present', 'Absent', 'Late', 'Leave', 'Half day', 'Marked']];
        foreach ($r['daily'] as $row) {
            $data['Daily'][] = [$row['date'], $row['class'], $row['section'], $row['session'], ...array_map(fn ($s) => $row['counts'][$s] ?? 0, ['present', 'absent', 'late', 'leave', 'half_day']), $row['marked']];
        }
        $data['Register'] = [['Student', 'Admission No', ...$r['dates'], 'Percentage']];
        foreach ($r['register'] as $row) {
            $data['Register'][] = [$row['student']->first_name.' '.$row['student']->last_name, $row['student']->admission_no, ...array_map(fn ($day) => AttendanceReports::SYMBOLS[$row['records']->get($day)?->status] ?? (in_array($day, $r['closed']) ? 'Off' : '-'), $r['dates']), $row['percentage'] ?? 'Unmarked'];
        }
        $data['Low attendance'] = [['Student', 'Admission No', 'Percentage']];
        foreach ($r['low'] as $row) {
            $data['Low attendance'][] = [$row['student']->first_name.' '.$row['student']->last_name, $row['student']->admission_no, $row['percentage']];
        }
        $data['Not marked today'] = [['Class ID', 'Section ID', 'Session ID', 'Missing students']];
        foreach ($r['missing'] as $row) {
            $data['Not marked today'][] = array_values($row);
        }
        if ($r['isAdmin']) {
            $data['Staff'] = [['Teacher', 'Employee ID', 'Month', 'Present', 'Absent', 'Late', 'Leave', 'Half day', 'Late minutes', 'Recorded days']];
            foreach ($r['staff'] as $row) {
                $data['Staff'][] = [$row['teacher']->name, $row['teacher']->employee_id, $row['month'], ...array_map(fn ($s) => $row['counts'][$s] ?? 0, ['present', 'absent', 'late', 'leave', 'half_day']), $row['late_minutes'], $row['days']];
            }
        }

        return array_map(fn ($title, $rows) => new class($title, $rows) implements FromArray, WithTitle
        {
            public function __construct(private string $name, private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function title(): string
            {
                return $this->name;
            }
        }, array_keys($data), array_values($data));
    }
}

<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Holiday;
use App\Models\Student;
use App\Models\StudentAttendance;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StudentAttendanceService
{
    /**
     * Get detailed month attendance calendar and summary for a student.
     *
     * @param Student $student
     * @param string|null $month YYYY-MM format
     * @return array
     * @throws ValidationException
     */
    public function getMonthAttendance(Student $student, ?string $month = null): array
    {
        $currentMonthStr = now()->format('Y-m');
        $month = $month ? trim($month) : $currentMonthStr;

        // Validate format YYYY-MM
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw ValidationException::withMessages([
                'month' => ['Invalid month format. Please use YYYY-MM (e.g., '.now()->format('Y-m').').'],
            ]);
        }

        $selectedMonth = CarbonImmutable::createFromFormat('!Y-m', $month);
        $currentMonth = CarbonImmutable::now()->startOfMonth();

        // Reject future months beyond current month
        if ($selectedMonth->startOfMonth()->gt($currentMonth)) {
            throw ValidationException::withMessages([
                'month' => ['Cannot view attendance for future months.'],
            ]);
        }

        $startOfMonth = $selectedMonth->startOfMonth()->toDateString();
        $endOfMonth = $selectedMonth->endOfMonth()->toDateString();

        // Efficient single query for student attendances in the month
        $attendances = StudentAttendance::where('student_id', $student->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());

        // Efficient single query for holidays overlapping this month
        $holidays = Holiday::whereDate('start_date', '<=', $endOfMonth)
            ->whereDate('end_date', '>=', $startOfMonth)
            ->get();

        $days = [];
        for ($d = 1; $d <= $selectedMonth->daysInMonth; $d++) {
            $dayDate = $selectedMonth->day($d);
            $dateStr = $dayDate->toDateString();
            $record = $attendances->get($dateStr);

            $isSunday = $dayDate->isSunday();
            $holidayMatch = $holidays->first(fn ($h) => $dayDate->betweenIncluded($h->start_date, $h->end_date));

            // Status must be one of: present | absent | late | leave | holiday | weekend | none
            $status = 'none';
            if ($record) {
                $rawStatus = strtolower(trim($record->status));
                $status = match ($rawStatus) {
                    'present', 'half_day' => 'present',
                    'late' => 'late',
                    'absent' => 'absent',
                    'leave' => 'leave',
                    default => 'none',
                };
            } elseif ($holidayMatch) {
                $status = 'holiday';
            } elseif ($isSunday) {
                $status = 'weekend';
            }

            $days[] = [
                'date' => $dateStr,
                'status' => $status,
                'holiday_name' => $holidayMatch?->name,
                'remarks' => $record?->remarks,
            ];
        }

        // Summary calculations: exclude Sundays and holidays
        $eligibleRecords = $attendances->reject(function ($r) use ($holidays) {
            $recordDate = CarbonImmutable::parse($r->date);
            return $recordDate->isSunday() || $holidays->contains(fn ($h) => $recordDate->betweenIncluded($h->start_date, $h->end_date));
        });

        $present = $eligibleRecords->whereIn('status', ['present', 'half_day'])->count();
        $absent = $eligibleRecords->where('status', 'absent')->count();
        $late = $eligibleRecords->where('status', 'late')->count();
        $leave = $eligibleRecords->where('status', 'leave')->count();
        $totalWorkingDays = $eligibleRecords->count();

        // Percentage rule: (present + late) / working days * 100
        $percentage = $totalWorkingDays > 0
            ? round((($present + $late) / $totalWorkingDays) * 100, 1)
            : 0.0;

        return [
            'month' => $month,
            'days' => $days,
            'summary' => [
                'totalWorkingDays' => $totalWorkingDays,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'leave' => $leave,
                'percentage' => $percentage,
            ],
        ];
    }

    /**
     * Get session/year-level summary with overall percentage and month-wise breakdown for charts.
     *
     * @param Student $student
     * @return array
     */
    public function getAttendanceSummary(Student $student): array
    {
        $sessionId = $student->academic_session_id;

        $query = StudentAttendance::where('student_id', $student->id);
        if ($sessionId) {
            $query->where('academic_session_id', $sessionId);
        }

        $records = $query->orderBy('date')->get();

        // Fallback: if session filter returned no records, look at current calendar year records
        if ($records->isEmpty() && $sessionId) {
            $records = StudentAttendance::where('student_id', $student->id)
                ->whereYear('date', now()->year)
                ->orderBy('date')
                ->get();
        }

        if ($records->isEmpty()) {
            return [
                'overall_percentage' => 0.0,
                'total_counts' => [
                    'totalWorkingDays' => 0,
                    'present' => 0,
                    'absent' => 0,
                    'late' => 0,
                    'leave' => 0,
                ],
                'months' => [],
            ];
        }

        $minDate = $records->min('date');
        $maxDate = $records->max('date');

        $holidays = Holiday::whereDate('start_date', '<=', $maxDate)
            ->whereDate('end_date', '>=', $minDate)
            ->get();

        $eligibleRecords = $records->reject(function ($r) use ($holidays) {
            $date = CarbonImmutable::parse($r->date);
            return $date->isSunday() || $holidays->contains(fn ($h) => $date->betweenIncluded($h->start_date, $h->end_date));
        });

        $totalPresent = $eligibleRecords->whereIn('status', ['present', 'half_day'])->count();
        $totalAbsent = $eligibleRecords->where('status', 'absent')->count();
        $totalLate = $eligibleRecords->where('status', 'late')->count();
        $totalLeave = $eligibleRecords->where('status', 'leave')->count();
        $totalWorkingDays = $eligibleRecords->count();

        $overallPercentage = $totalWorkingDays > 0
            ? round((($totalPresent + $totalLate) / $totalWorkingDays) * 100, 1)
            : 0.0;

        // Group by month YYYY-MM
        $grouped = $eligibleRecords->groupBy(fn ($r) => Carbon::parse($r->date)->format('Y-m'));

        $monthsList = [];
        foreach ($grouped as $monthKey => $monthRecords) {
            $mPresent = $monthRecords->whereIn('status', ['present', 'half_day'])->count();
            $mAbsent = $monthRecords->where('status', 'absent')->count();
            $mLate = $monthRecords->where('status', 'late')->count();
            $mLeave = $monthRecords->where('status', 'leave')->count();
            $mWorkingDays = $monthRecords->count();

            $mPercentage = $mWorkingDays > 0
                ? round((($mPresent + $mLate) / $mWorkingDays) * 100, 1)
                : 0.0;

            $monthDate = Carbon::createFromFormat('!Y-m', $monthKey);

            $monthsList[] = [
                'month' => $monthKey,
                'label' => $monthDate->format('M Y'),
                'percentage' => $mPercentage,
                'present' => $mPresent,
                'absent' => $mAbsent,
                'late' => $mLate,
                'leave' => $mLeave,
                'totalWorkingDays' => $mWorkingDays,
            ];
        }

        return [
            'overall_percentage' => $overallPercentage,
            'total_counts' => [
                'totalWorkingDays' => $totalWorkingDays,
                'present' => $totalPresent,
                'absent' => $totalAbsent,
                'late' => $totalLate,
                'leave' => $totalLeave,
            ],
            'months' => array_values($monthsList),
        ];
    }
}

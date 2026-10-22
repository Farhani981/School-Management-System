<?php

namespace App\Services;

use App\Models\ExamSchedule;
use App\Models\FeeVoucher;
use App\Models\Holiday;
use App\Models\LibraryMember;
use App\Models\LibraryTransaction;
use App\Models\SchoolEvent;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\TimetableEntry;
use Carbon\Carbon;
use DateTime;

class StudentDashboardService
{
    /**
     * Build the consolidated JSON payload for the student portal dashboard.
     * Guaranteed safe defaults and zero N+1 queries.
     */
    public function getDashboardData(Student $student): array
    {
        return [
            'student' => $this->getStudentProfile($student),
            'attendance' => $this->getMonthlyAttendance($student),
            'fees' => $this->getFeeStatus($student),
            'exams' => $this->getUpcomingExams($student),
            'library' => $this->getLibraryStats($student),
            'notice' => $this->getLatestNotices($student),
            'todayClasses' => $this->getTodayClasses($student),
            'activeQuiz' => null,
            'latestMaterial' => [],
        ];
    }

    /**
     * Student Profile Summary.
     */
    protected function getStudentProfile(Student $student): array
    {
        return [
            'full_name' => $student->full_name,
            'class' => $student->schoolClass?->name ?? 'N/A',
            'section' => $student->section?->name ?? 'N/A',
            'roll_no' => $student->roll_number,
            'admission_no' => $student->admission_no,
            'profile_photo_url' => $student->photo ? asset('storage/' . $student->photo) : null,
        ];
    }

    /**
     * Current month attendance aggregation.
     */
    protected function getMonthlyAttendance(Student $student): array
    {
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $counts = StudentAttendance::where('student_id', $student->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->selectRaw("
                SUM(CASE WHEN LOWER(status) IN ('present', 'half_day') THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN LOWER(status) = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN LOWER(status) = 'late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN LOWER(status) = 'leave' THEN 1 ELSE 0 END) as leave_days,
                COUNT(*) as total_days
            ")
            ->first();

        $present = (int) ($counts->present_days ?? 0);
        $absent = (int) ($counts->absent_days ?? 0);
        $late = (int) ($counts->late_days ?? 0);
        $leave = (int) ($counts->leave_days ?? 0);
        $totalWorkingDays = (int) ($counts->total_days ?? 0);

        $percentage = $totalWorkingDays > 0
            ? round((($present + $late) / $totalWorkingDays) * 100, 1)
            : 0.0;

        return [
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'leave' => $leave,
            'total_working_days' => $totalWorkingDays,
            'percentage' => $percentage,
        ];
    }

    /**
     * Fee status, pending dues, and latest unpaid challan.
     */
    protected function getFeeStatus(Student $student): array
    {
        $summary = app(StudentFeeService::class)->getSummary($student);

        return [
            'status' => $summary['status'],
            'total_pending_amount' => $summary['totalPending'],
            'latest_unpaid_challan' => $summary['latest'],
        ];
    }

    /**
     * Count of upcoming exams and next 3 upcoming exam items.
     */
    protected function getUpcomingExams(Student $student): array
    {
        if (! $student->class_id) {
            return ['count' => 0, 'upcoming' => []];
        }

        $baseQuery = ExamSchedule::join('exam_subjects', 'exam_schedules.exam_subject_id', '=', 'exam_subjects.id')
            ->join('subjects', 'exam_subjects.subject_id', '=', 'subjects.id')
            ->where('exam_subjects.class_id', $student->class_id)
            ->where('exam_schedules.exam_date', '>=', today()->toDateString())
            ->where(function ($q) use ($student) {
                $q->whereNull('exam_schedules.section_id')
                  ->orWhere('exam_schedules.section_id', $student->section_id);
            });

        $count = (clone $baseQuery)->count();

        $upcoming = $baseQuery->orderBy('exam_schedules.exam_date')
            ->orderBy('exam_schedules.start_time')
            ->select([
                'subjects.name as subject',
                'exam_schedules.exam_date as date',
                'exam_schedules.start_time',
                'exam_schedules.end_time',
                'exam_schedules.room',
            ])
            ->limit(3)
            ->get()
            ->map(function ($item) {
                $start = $item->start_time ? Carbon::parse($item->start_time)->format('g:i A') : null;
                $end = $item->end_time ? Carbon::parse($item->end_time)->format('g:i A') : null;
                $timeString = ($start && $end) ? ($start . ' - ' . $end) : ($start ?? 'TBA');

                return [
                    'subject' => $item->subject,
                    'date' => $item->date ? Carbon::parse($item->date)->format('Y-m-d') : null,
                    'time' => $timeString,
                    'room' => $item->room ?: 'TBA',
                ];
            })
            ->values()
            ->all();

        return [
            'count' => $count,
            'upcoming' => $upcoming,
        ];
    }

    /**
     * Library statistics: active loans and overdue count.
     */
    protected function getLibraryStats(Student $student): array
    {
        $member = LibraryMember::where('student_id', $student->id)
            ->where('member_type', 'Student')
            ->first();

        if (! $member) {
            return [
                'issued_count' => 0,
                'overdue_count' => 0,
            ];
        }

        $activeLoans = LibraryTransaction::where('member_id', $member->id)
            ->where('status', 'Issued')
            ->get(['id', 'due_date']);

        $issuedCount = $activeLoans->count();
        $today = today()->toDateString();
        $overdueCount = $activeLoans->filter(fn ($loan) => $loan->due_date && $loan->due_date->lt($today))->count();

        return [
            'issued_count' => $issuedCount,
            'overdue_count' => $overdueCount,
        ];
    }

    /**
     * Latest 3 announcements / events.
     */
    protected function getLatestNotices(Student $student): array
    {
        return SchoolEvent::orderByDesc('date')
            ->limit(3)
            ->get(['id', 'title', 'description', 'date', 'type'])
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'date' => $event->date?->format('Y-m-d'),
                    'type' => $event->type,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Today's timetable schedule. Empty on Sunday or official holiday.
     */
    protected function getTodayClasses(Student $student): array
    {
        $now = now();
        $todayStr = today()->toDateString();

        // 7 is Sunday in ISO-8601
        $isSunday = ($now->dayOfWeekIso === 7);
        $isHoliday = Holiday::where('start_date', '<=', $todayStr)
            ->where('end_date', '>=', $todayStr)
            ->exists();

        if ($isSunday || $isHoliday || ! $student->class_id) {
            return [];
        }

        return TimetableEntry::with(['period', 'subject', 'teacher'])
            ->where('class_id', $student->class_id)
            ->where(function ($q) use ($student) {
                $q->whereNull('section_id')
                  ->orWhere('section_id', $student->section_id);
            })
            ->where('day_of_week', $now->dayOfWeekIso)
            ->where('status', TimetableEntry::STATUS_PUBLISHED)
            ->get()
            ->sortBy(fn ($entry) => $entry->period?->start_time ?? '99:99')
            ->map(function ($entry) {
                $period = $entry->period;
                return [
                    'period_no' => $period?->sort_order ?? $period?->name ?? null,
                    'time' => $period ? $period->formatted_time_range : null,
                    'start_time' => $period?->start_time,
                    'end_time' => $period?->end_time,
                    'subject' => $entry->subject?->name ?? 'Subject',
                    'teacher_name' => $entry->teacher?->name ?? 'Teacher',
                    'room' => $entry->room ?? null,
                ];
            })
            ->values()
            ->all();
    }
}

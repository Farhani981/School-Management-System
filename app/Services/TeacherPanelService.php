<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TeacherPanelService
{
    public function assignments(User $user)
    {
        return TeacherAssignment::where('teacher_id', $user->teacher?->status === 'Active' ? $user->teacher->id : 0)
            ->with(['schoolClass', 'section', 'subject', 'session'])->orderBy('class_id')->orderBy('section_id')->get();
    }

    public function roster(TeacherAssignment $assignment)
    {
        return Student::where('status', 'Active')->where('class_id', $assignment->class_id)
            ->where('section_id', $assignment->section_id)->where('academic_session_id', $assignment->session_id)
            ->orderByRaw('CAST(roll_number AS DECIMAL(12, 2))')->orderBy('roll_number')->orderBy('id');
    }

    public function cards(User $user): array
    {
        $cards = [];
        foreach ($this->assignments($user) as $assignment) {
            $roster = $this->roster($assignment);
            $count = (clone $roster)->count();
            $todayAttendance = StudentAttendance::whereIn('student_id', (clone $roster)->select('students.id'))
                ->where('school_class_id', $assignment->class_id)->where('section_id', $assignment->section_id)->where('academic_session_id', $assignment->session_id)
                ->whereDate('date', today())->get(['status']);
            $marked = $todayAttendance->count();
            $present = $todayAttendance->where('status', 'present')->count();
            $absent = $todayAttendance->where('status', 'absent')->count();
            $late = $todayAttendance->where('status', 'late')->count();

            $overallStats = StudentAttendance::whereIn('student_id', (clone $roster)->select('students.id'))
                ->where('school_class_id', $assignment->class_id)->where('section_id', $assignment->section_id)->where('academic_session_id', $assignment->session_id)
                ->where('date', '<=', today())
                ->selectRaw("COUNT(id) as total, SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) as attended")
                ->first();
            $overallPercent = ($overallStats && $overallStats->total > 0)
                ? (int) round((100.0 * $overallStats->attended) / $overallStats->total)
                : ($count > 0 ? 100 : 0);

            $cards[] = [
                'assignment' => $assignment,
                'can_mark_attendance' => app(FirstPeriodAttendance::class)->allows($user, $assignment->class_id, $assignment->section_id, $assignment->session_id),
                'count' => $count,
                'marked' => $count > 0 && $marked === $count,
                'marked_count' => $marked,
                'present_count' => $present,
                'absent_count' => $absent,
                'late_count' => $late,
                'overall_percentage' => $overallPercent,
            ];
        }
        return $cards;
    }

    public static function cacheKey(User $user): string
    {
        return 'teacher-panel:v2:'.$user->id.':'.today()->toDateString().':'.Cache::get('dashboard:revision', 'initial');
    }

    public function dashboard(User $user): array
    {
        return Cache::remember(self::cacheKey($user), 30, function () use ($user) {
            $cards = $this->cards($user);
            $groups = collect($cards)->unique(fn ($card) => implode(':', [$card['assignment']->class_id, $card['assignment']->section_id, $card['assignment']->session_id]));
            $lowAttendanceCount = 0;
            $registers = [];
            foreach ($groups as $card) {
                $assignment = $card['assignment'];
                $stats = StudentAttendance::select('student_id')->selectRaw('COUNT(*) as marked_days')
                    ->selectRaw("SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) as attended_days")
                    ->where('date', '<=', today())->where('academic_session_id', $assignment->session_id)
                    ->where('school_class_id', $assignment->class_id)->where('section_id', $assignment->section_id)->groupBy('student_id');
                $lowAttendanceCount += $this->roster($assignment)->joinSub($stats, 'attendance_stats', 'students.id', '=', 'attendance_stats.student_id')
                    ->whereRaw('100.0 * attended_days / marked_days < 75')
                    ->count();
                $students = $this->roster($assignment)->get(['id', 'first_name', 'last_name', 'roll_number']);
                $attendance = StudentAttendance::whereIn('student_id', $students->modelKeys())
                    ->where('school_class_id', $assignment->class_id)->where('section_id', $assignment->section_id)
                    ->where('academic_session_id', $assignment->session_id)->whereDate('date', today())->get()->keyBy('student_id');
                $registers[] = $card + ['students' => $students, 'attendance' => $attendance,
                    'present_count' => $attendance->where('status', 'present')->count(),
                    'subjects' => collect($cards)->filter(fn ($item) => $item['assignment']->class_id === $assignment->class_id
                        && $item['assignment']->section_id === $assignment->section_id && $item['assignment']->session_id === $assignment->session_id)
                        ->map(fn ($item) => $item['assignment']->subject?->name)->filter()->unique()->implode(', '),
                    'is_class_teacher' => collect($cards)->contains(fn ($item) => $item['assignment']->class_id === $assignment->class_id
                        && $item['assignment']->section_id === $assignment->section_id && $item['assignment']->session_id === $assignment->session_id && $item['assignment']->is_class_teacher)];
            }
            $periods = $this->todayPeriods($user);
            $todayGroups = $groups;
            if (count($periods) > 0) {
                $scheduledIds = collect($periods)->pluck('assignment_id')->filter();
                $scheduledGroups = collect($cards)->filter(fn ($card) => $scheduledIds->contains($card['assignment']->id))
                    ->map(fn ($card) => implode(':', [$card['assignment']->class_id, $card['assignment']->section_id, $card['assignment']->session_id]));
                $todayGroups = $groups->filter(fn ($card) => $scheduledGroups->contains(implode(':', [$card['assignment']->class_id, $card['assignment']->section_id, $card['assignment']->session_id])));
            }
            $todayGroups = $todayGroups->filter(fn ($card) => $card['can_mark_attendance']);
            $pendingAttendance = $todayGroups->filter(fn ($card) => $card['count'] > 0 && ! $card['marked'])->count();
            $pendingQuizMarks = $user->teacher ? app(\App\Services\QuizService::class)->getPendingMarksCount($user->teacher) : 0;
            $overdueNotesCount = $user->teacher ? \App\Models\StudentNote::where('teacher_id', $user->teacher->id)->where('is_done', false)->whereNotNull('follow_up_date')->where('follow_up_date', '<', today())->count() : 0;
            $notices = $this->notices();
            // Assignments contain weekly load, but no scheduled day/start/end. Never infer periods from them.
            return ['cards' => $cards, 'classCount' => $groups->count(), 'pendingAttendance' => $pendingAttendance,
                'pendingQuizMarks' => $pendingQuizMarks,
                'overdueNotesCount' => $overdueNotesCount,
                'registers' => $registers, 'periods' => $periods, 'scheduleAvailable' => count($periods) > 0,
                'registerTotal' => $todayGroups->filter(fn ($card) => $card['count'] > 0)->count(),
                'lowAttendanceCount' => $lowAttendanceCount,
                'notices' => $notices, 'latestNotice' => $notices->first(),
                'dashboardDate' => today()->toDateString(), 'dashboardNow' => now()->toIso8601String(),
                'dashboardTimezone' => config('app.timezone')];
        });
    }

    /**
     * Timetable adapter for teacher dashboard.
     * Each period: assignment_id (null for free), starts_at/ends_at (ISO with timezone).
     * Only return today's periods for this active teacher. The view switches automatically.
     */
    public function todayPeriods(User $user): array
    {
        return app(\App\Services\TimetableService::class)->getTodayTeacherPeriods($user);
    }

    public function notices()
    {
        // Phase 1: school-wide calendar announcements are the existing notice source.
        // Do not expose an unknown notices schema without audience/publishing rules.
        return Schema::hasTable('events') ? \App\Models\SchoolEvent::whereIn('type', ['event', 'meeting', 'exam', 'holiday'])
            ->where('date', '>=', today())->orderByDesc('created_at')->limit(5)->get(['id', 'title', 'description', 'date', 'type']) : collect();
    }
}

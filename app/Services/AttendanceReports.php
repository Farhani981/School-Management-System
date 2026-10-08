<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use App\Policies\AttendancePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AttendanceReports
{
    public const SYMBOLS = ['present' => 'P', 'absent' => 'A', 'late' => 'L', 'leave' => 'LV', 'half_day' => 'H'];

    public function scope($query, User $user, string $class = 'school_class_id', string $session = 'academic_session_id')
    {
        if (AttendancePolicy::admin($user)) {
            return $query;
        }
        abort_unless(strtolower($user->role) === 'teacher' && $user->teacher?->status === 'Active', 403);

        return $query->whereExists(function ($q) use ($user, $class, $session, $query) {
            $table = $query->getModel()->getTable();
            $q->select(DB::raw(1))->from('teacher_assignments')->where('teacher_id', $user->teacher->id)->whereColumn('teacher_assignments.class_id', $table.'.'.$class)->whereColumn('teacher_assignments.section_id', $table.'.section_id')->whereColumn('teacher_assignments.session_id', $table.'.'.$session);
        });
    }

    private function filters($query, array $filters, bool $students = false)
    {
        foreach (['school_class_id', 'section_id', 'academic_session_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($students && $field === 'school_class_id' ? 'class_id' : $field, $filters[$field]);
            }
        }

        return $query;
    }

    public function percentage($records): ?float
    {
        $count = $records->where('status', '!=', 'leave')->count();
        if (! $count) {
            return null;
        }
        $credit = $records->whereIn('status', ['present', 'late'])->count() + $records->where('status', 'half_day')->count() / 2;

        return round($credit / $count * 100, 1);
    }

    public function build(array $filters, User $user): array
    {
        $records = $this->filters($this->scope(StudentAttendance::with(['student', 'schoolClass', 'section', 'session']), $user), $filters)->whereDate('date', '>=', $filters['from'])->whereDate('date', '<=', $filters['to'])->orderBy('date')->get();
        $holidays = Holiday::whereDate('start_date', '<=', $filters['to'])->whereDate('end_date', '>=', $filters['from'])->get();
        $dates = [];
        $closed = [];
        for ($date = CarbonImmutable::parse($filters['from']); $date->lte(CarbonImmutable::parse($filters['to'])); $date = $date->addDay()) {
            $key = $date->toDateString();
            $dates[] = $key;
            if ($date->isSunday() || $holidays->contains(fn ($h) => $date->betweenIncluded($h->start_date, $h->end_date))) {
                $closed[] = $key;
            }
        }
        $daily = $records->groupBy(fn ($r) => $r->date->toDateString().'|'.$r->school_class_id.'|'.$r->section_id.'|'.$r->academic_session_id)->map(function ($items) {
            $first = $items->first();

            return ['date' => $first->date->toDateString(), 'class' => $first->schoolClass?->name ?? 'Legacy / unassigned', 'section' => $first->section?->name ?? 'Unassigned', 'session' => $first->session?->name ?? 'Unassigned', 'counts' => $items->countBy('status')->all(), 'marked' => $items->count()];
        })->values();
        $roster = $this->filters($this->scope(Student::where('status', 'Active'), $user, 'class_id'), $filters)->orderBy('class_id')->orderBy('roll_number')->get();
        $students = $roster->concat($records->pluck('student')->filter())->unique('id');
        $grouped = $records->groupBy('student_id');
        $register = $students->map(function ($student) use ($grouped, $closed) {
            $all = $grouped->get($student->id, collect());
            $schoolDays = $all->reject(fn ($r) => in_array($r->date->toDateString(), $closed));

            return ['student' => $student, 'records' => $all->keyBy(fn ($r) => $r->date->toDateString()), 'percentage' => $this->percentage($schoolDays), 'recorded_days' => $schoolDays->count()];
        })->values();
        $today = now()->toDateString();
        $todayIds = $this->scope(StudentAttendance::query(), $user)->whereDate('date', $today)->pluck('student_id');
        $missing = $roster->whereNotIn('id', $todayIds->all())->groupBy(fn ($s) => $s->class_id.'|'.$s->section_id.'|'.$s->academic_session_id)->map(fn ($group) => ['class_id' => $group->first()->class_id, 'section_id' => $group->first()->section_id, 'session_id' => $group->first()->academic_session_id, 'missing' => $group->count()])->values();
        $staff = AttendancePolicy::admin($user) ? StaffAttendance::with('teacher')->whereDate('date', '>=', $filters['from'])->whereDate('date', '<=', $filters['to'])->get()->groupBy(fn ($r) => $r->teacher_id.'|'.$r->date->format('Y-m'))->map(fn ($items) => ['month' => $items->first()->date->format('Y-m'), 'teacher' => $items->first()->teacher, 'counts' => $items->countBy('status')->all(), 'late_minutes' => $items->sum('late_minutes'), 'days' => $items->count()])->values() : collect();

        return compact('filters', 'dates', 'closed', 'holidays', 'daily', 'register', 'missing', 'staff') + ['low' => $register->filter(fn ($row) => $row['percentage'] !== null && $row['percentage'] < 75), 'options' => app(AttendanceRecords::class)->options($user), 'isAdmin' => AttendancePolicy::admin($user)];
    }

    public function profile(Student $student, string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);
        $end = $start->endOfMonth();
        $records = $student->attendances()->whereDate('date', '>=', $start)->whereDate('date', '<=', $end)->get()->keyBy(fn ($r) => $r->date->toDateString());
        $holidays = Holiday::whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->get();
        $days = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $days[] = ['date' => $day, 'status' => $records->get($day->toDateString())?->status, 'holiday' => $holidays->first(fn ($h) => $day->betweenIncluded($h->start_date, $h->end_date))?->name, 'sunday' => $day->isSunday()];
        }
        $eligible = $records->reject(fn ($r) => CarbonImmutable::parse($r->date)->isSunday() || $holidays->contains(fn ($h) => $r->date->betweenIncluded($h->start_date,$h->end_date)));

        return ['month' => $start, 'days' => $days, 'percentage' => $this->percentage($eligible), 'recorded' => $eligible->count()];
    }
}

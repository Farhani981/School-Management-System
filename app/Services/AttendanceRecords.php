<?php

namespace App\Services;

use App\Jobs\SendAbsenceNotification;
use App\Models\AcademicSession;
use App\Models\Activity;
use App\Models\Holiday;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use App\Policies\AttendancePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AttendanceRecords
{
    public function options(User $user, ?string $date = null): array
    {
        $classes = SchoolClass::query();
        if (! AttendancePolicy::admin($user)) {
            $assignments = \App\Models\TeacherAssignment::where('teacher_id', $user->teacher?->id ?? 0)->get()
                ->filter(fn ($assignment) => app(FirstPeriodAttendance::class)->allows($user, $assignment->class_id, $assignment->section_id, $assignment->session_id, $date));
            return ['classes' => $classes->whereIn('id', $assignments->pluck('class_id'))->orderBy('id')->get(),
                'sections' => SchoolSection::whereIn('id', $assignments->pluck('section_id'))->orderBy('name')->get(),
                'sessions' => AcademicSession::whereIn('id', $assignments->pluck('session_id'))->orderBy('id')->get()];
        }

        return ['classes' => $classes->orderBy('id')->get(), 'sections' => SchoolSection::orderBy('name')->get(), 'sessions' => AcademicSession::orderBy('id')->get()];
    }

    public function roster(array $data)
    {
        return Student::where('status', 'Active')->where('class_id', $data['school_class_id'])->where('section_id', $data['section_id'])->where('academic_session_id', $data['academic_session_id'])->orderByRaw('CAST(roll_number AS DECIMAL(12, 2))')->orderBy('roll_number')->orderBy('id');
    }

    public function warnings(string $date): array
    {
        $warnings = [];
        if (CarbonImmutable::parse($date)->isSunday()) {
            $warnings[] = 'This date is a Sunday. Confirm that school was open before saving.';
        }
        foreach (Holiday::whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->get() as $holiday) {
            $warnings[] = 'Holiday: '.$holiday->name.'. Confirm that attendance should be recorded.';
        }

        return $warnings;
    }

    public function students(array $data, User $user): void
    {
        Gate::forUser($user)->authorize('mark', [SchoolClass::findOrFail($data['school_class_id']), $data['section_id'], $data['academic_session_id'], $data['date']]);
        DB::transaction(function () use ($data, $user) {
            SchoolClass::whereKey($data['school_class_id'])->lockForUpdate()->firstOrFail();
            $students = $this->roster($data)->lockForUpdate()->get()->keyBy('id');
            $submitted = collect($data['rows'])->pluck('student_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($submitted !== $students->keys()->sort()->values()->all()) {
                throw ValidationException::withMessages(['rows' => 'The active roster changed or includes students outside this class/session. Reload before saving.']);
            }
            $existing = StudentAttendance::whereIn('student_id', $students->keys())->whereDate('date', $data['date'])->lockForUpdate()->get()->keyBy('student_id');
            $rows = [];
            $notify = [];
            foreach ($data['rows'] as $row) {
                $old = $existing->get($row['student_id']);
                if ($row['status'] === 'absent' && $old?->status !== 'absent') {
                    $notify[] = $row['student_id'];
                }
                $rows[] = ['student_id' => $row['student_id'], 'academic_session_id' => $data['academic_session_id'], 'school_class_id' => $data['school_class_id'], 'section_id' => $data['section_id'], 'date' => $data['date'], 'attendance_date' => $data['date'], 'status' => $row['status'], 'remarks' => $row['remarks'] ?? null, 'marked_by' => $user->id, 'created_at' => $old?->created_at ?? now(), 'updated_at' => now()];
            }
            StudentAttendance::upsert($rows, ['student_id', 'date'], ['academic_session_id', 'school_class_id', 'section_id', 'attendance_date', 'status', 'remarks', 'marked_by', 'updated_at']);
            DashboardStatsService::invalidate();
            DB::afterCommit(fn () => DashboardStatsService::invalidate());
            if (Schema::hasTable('activities')) {
                Activity::create(['description' => 'Student attendance saved for '.$data['date'].' (class #'.$data['school_class_id'].', section #'.$data['section_id'].').', 'type' => 'attendance', 'subject' => 'school_classes:'.$data['school_class_id'], 'user_id' => $user->id, 'created_at' => now()]);
            }
            if (! empty($data['notify_absent'])) {
                foreach (StudentAttendance::whereIn('student_id', $notify)->whereDate('date', $data['date'])->pluck('id') as $id) {
                    SendAbsenceNotification::dispatch($id)->afterCommit();
                }
            }
        }, 3);
    }

    public function staffRoster(string $date)
    {
        return Teacher::where('status', '!=', 'Inactive')->whereDate('joining_date', '<=', $date)->with(['leaves' => fn ($q) => $q->with('leaveType')->where('status', 'Approved')->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)])->orderBy('name');
    }

    public function staff(array $data): void
    {
        DB::transaction(function () use ($data) {
            $teachers = $this->staffRoster($data['date'])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if (collect($data['rows'])->pluck('teacher_id')->map(fn ($id) => (int) $id)->sort()->values()->all() !== $teachers->keys()->sort()->values()->all()) {
                throw ValidationException::withMessages(['rows' => 'The staff roster changed. Reload before saving.']);
            }
            $rows = [];
            $legacy = [];
            foreach ($data['rows'] as $row) {
                $teacher = $teachers[$row['teacher_id']];
                app(TeacherLeaves::class)->assertUnprocessed($teacher->id, $data['date'], $data['date']);
                $status = $teacher->leaves->isNotEmpty() ? 'leave' : $row['status'];
                $late = 0;
                if (! empty($row['check_in']) && $status !== 'leave') {
                    $late = max(0, (int) CarbonImmutable::parse($data['date'].' '.config('attendance.staff_start_time'))->diffInMinutes(CarbonImmutable::parse($data['date'].' '.$row['check_in']), false));
                }
                if ($status === 'present' && $late > 0) {
                    $status = 'late';
                }
                $rows[] = ['teacher_id' => $teacher->id, 'date' => $data['date'], 'status' => $status, 'check_in' => $status === 'leave' ? null : ($row['check_in'] ?? null), 'check_out' => $status === 'leave' ? null : ($row['check_out'] ?? null), 'late_minutes' => $late, 'remarks' => $row['remarks'] ?? null, 'created_at' => now(), 'updated_at' => now()];
                $legacy[] = ['teacher_id' => $teacher->id, 'date' => $data['date'], 'status' => $status === 'half_day' ? 'Half Day' : ucfirst($status), 'created_at' => now(), 'updated_at' => now()];
            }
            StaffAttendance::upsert($rows, ['teacher_id', 'date'], ['status', 'check_in', 'check_out', 'late_minutes', 'remarks', 'updated_at']);
            DashboardStatsService::invalidate();
            DB::afterCommit(fn () => DashboardStatsService::invalidate());
            TeacherAttendance::upsert($legacy, ['teacher_id', 'date'], ['status', 'updated_at']);
        }, 3);
    }
}

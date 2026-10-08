<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Period;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\TimetableEntry;
use App\Models\TimetableOverride;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TimetableService
{
    /**
     * Build weekly schedule matrix for a specific Class + Section in a Session.
     */
    public function getSectionMatrix(int $sessionId, int $classId, int $sectionId): array
    {
        $periods = Period::ordered()->get();
        $days = collect(TimetableEntry::DAYS);

        $entries = TimetableEntry::with(['subject', 'teacher', 'period'])
            ->where('session_id', $sessionId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->get();

        $grid = [];
        foreach ($days as $dayNum => $dayName) {
            $grid[$dayNum] = [];
            foreach ($periods as $period) {
                $grid[$dayNum][$period->id] = $entries->first(fn ($e) => 
                    $e->day_of_week === $dayNum && $e->period_id === $period->id
                );
            }
        }

        // Check if any entries are published
        $isPublished = $entries->where('status', TimetableEntry::STATUS_PUBLISHED)->isNotEmpty();
        $totalAssigned = $entries->count();

        // Valid teacher assignments for this section
        $validAssignments = TeacherAssignment::with(['subject', 'teacher'])
            ->where('session_id', $sessionId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->get();

        return [
            'periods' => $periods,
            'days' => $days,
            'grid' => $grid,
            'entries' => $entries,
            'isPublished' => $isPublished,
            'totalAssigned' => $totalAssigned,
            'validAssignments' => $validAssignments,
        ];
    }

    /**
     * Build weekly schedule matrix for a specific Teacher.
     */
    public function getTeacherMatrix(int $sessionId, int $teacherId): array
    {
        $periods = Period::ordered()->get();
        $days = collect(TimetableEntry::DAYS);

        $entries = TimetableEntry::with(['schoolClass', 'section', 'subject', 'period'])
            ->where('session_id', $sessionId)
            ->where('teacher_id', $teacherId)
            ->where('status', TimetableEntry::STATUS_PUBLISHED)
            ->get();

        $grid = [];
        foreach ($days as $dayNum => $dayName) {
            $grid[$dayNum] = [];
            foreach ($periods as $period) {
                $grid[$dayNum][$period->id] = $entries->first(fn ($e) => 
                    $e->day_of_week === $dayNum && $e->period_id === $period->id
                );
            }
        }

        return [
            'periods' => $periods,
            'days' => $days,
            'grid' => $grid,
            'totalClasses' => $entries->count(),
            'routineEntries' => $entries->sortBy('day_of_week')->groupBy('period_id'),
        ];
    }

    /**
     * Validate and save an entry with comprehensive conflict detection.
     */
    public function saveEntry(array $data, ?int $userId = null): TimetableEntry
    {
        $sessionId = (int) $data['session_id'];
        $classId = (int) $data['class_id'];
        $sectionId = (int) $data['section_id'];
        $subjectId = (int) $data['subject_id'];
        $teacherId = (int) $data['teacher_id'];
        $dayOfWeek = (int) $data['day_of_week'];
        $periodId = (int) $data['period_id'];
        $room = $data['room'] ?? null;
        $entryId = $data['entry_id'] ?? null;

        $period = Period::findOrFail($periodId);
        if ($period->is_break) {
            throw ValidationException::withMessages([
                'period_id' => 'Cannot assign subjects during break periods.'
            ]);
        }

        // 1. Verify that teacher assignment is valid for this class/section/subject
        $validAssignment = TeacherAssignment::where('session_id', $sessionId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('teacher_id', $teacherId)
            ->exists();

        if (!$validAssignment) {
            throw ValidationException::withMessages([
                'teacher_id' => 'This teacher is not assigned to teach this subject for this class/section.'
            ]);
        }

        // 2. Check for section conflict (section cannot have two subjects in same period & day)
        $sectionConflict = TimetableEntry::where('session_id', $sessionId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_id', $periodId)
            ->when($entryId, fn ($q) => $q->where('id', '!=', $entryId))
            ->first();

        if ($sectionConflict) {
            throw ValidationException::withMessages([
                'period_id' => 'This class section already has ' . ($sectionConflict->subject?->name ?? 'a subject') . ' scheduled in this period.'
            ]);
        }

        // 3. Check for teacher conflict (teacher cannot teach two classes in same period & day)
        $teacherConflict = TimetableEntry::where('session_id', $sessionId)
            ->where('teacher_id', $teacherId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_id', $periodId)
            ->when($entryId, fn ($q) => $q->where('id', '!=', $entryId))
            ->first();

        if ($teacherConflict) {
            $conflictClass = $teacherConflict->schoolClass?->name . ' - ' . $teacherConflict->section?->name;
            throw ValidationException::withMessages([
                'teacher_id' => 'Teacher is already scheduled to teach ' . $conflictClass . ' in this period.'
            ]);
        }

        return TimetableEntry::updateOrCreate(
            [
                'session_id' => $sessionId,
                'class_id' => $classId,
                'section_id' => $sectionId,
                'day_of_week' => $dayOfWeek,
                'period_id' => $periodId,
            ],
            [
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId,
                'room' => $room,
                'status' => TimetableEntry::STATUS_DRAFT,
                'created_by' => $userId,
            ]
        );
    }

    public function repeatDailyRoutine(array $data, int $userId): int
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data, $userId) {
            $source = TimetableEntry::where('session_id', $data['session_id'])->where('class_id', $data['class_id'])
                ->where('section_id', $data['section_id'])->where('day_of_week', $data['source_day'])->lockForUpdate()->get();
            if ($source->isEmpty()) {
                throw ValidationException::withMessages(['source_day' => 'Add the daily periods before applying the routine.']);
            }
            $count = 0;
            foreach (range(1, (int) $data['teaching_days']) as $day) {
                foreach ($source as $entry) {
                    $existing = TimetableEntry::where('session_id', $entry->session_id)->where('class_id', $entry->class_id)
                        ->where('section_id', $entry->section_id)->where('day_of_week', $day)->where('period_id', $entry->period_id)->first();
                    if ($existing && ($existing->teacher_id !== $entry->teacher_id || $existing->subject_id !== $entry->subject_id)) {
                        throw ValidationException::withMessages(['source_day' => TimetableEntry::DAYS[$day].' has a different assignment. Clear that period before applying the daily routine.']);
                    }
                    $this->saveEntry(['session_id' => $entry->session_id, 'class_id' => $entry->class_id,
                        'section_id' => $entry->section_id, 'subject_id' => $entry->subject_id, 'teacher_id' => $entry->teacher_id,
                        'day_of_week' => $day, 'period_id' => $entry->period_id, 'room' => $entry->room, 'entry_id' => $existing?->id], $userId);
                    $count++;
                }
            }
            return $count;
        });
    }

    public function saveTeacherRoutine(array $data, int $userId): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $userId) {
            $assignments = TeacherAssignment::where('teacher_id', $data['teacher_id'])->where('session_id', $data['session_id'])->get()->keyBy('id');
            $periods = Period::active()->where('is_break', false)->get()->keyBy('id');
            $rows = collect($data['periods'] ?? [])->filter(fn ($value) => $value !== null && $value !== '');
            foreach ($rows as $periodId => $assignmentId) {
                if (!$periods->has($periodId) || !$assignments->has($assignmentId)) {
                    throw ValidationException::withMessages(['periods' => 'Choose an active period and a class assigned to this teacher.']);
                }
            }
            // Replacing this teacher's schedule is atomic: any conflict restores the old routine.
            TimetableEntry::where('session_id', $data['session_id'])->where('teacher_id', $data['teacher_id'])->delete();
            foreach ($rows as $periodId => $assignmentId) {
                $assignment = $assignments[$assignmentId];
                foreach (range(1, (int) $data['teaching_days']) as $day) {
                    $entry = $this->saveEntry(['session_id' => $data['session_id'], 'teacher_id' => $data['teacher_id'],
                        'class_id' => $assignment->class_id, 'section_id' => $assignment->section_id,
                        'subject_id' => $assignment->subject_id, 'period_id' => $periodId, 'day_of_week' => $day], $userId);
                    $entry->update(['status' => TimetableEntry::STATUS_PUBLISHED]);
                }
            }
            \Illuminate\Support\Facades\Cache::forget(TeacherPanelService::cacheKey($assignments->first()?->teacher?->user ?? User::findOrFail($userId)));
        });
    }

    /**
     * Publish or unpublish all entries for a section.
     */
    public function setPublishStatus(int $sessionId, int $classId, int $sectionId, bool $publish): int
    {
        $status = $publish ? TimetableEntry::STATUS_PUBLISHED : TimetableEntry::STATUS_DRAFT;
        return TimetableEntry::where('session_id', $sessionId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->update(['status' => $status]);
    }

    /**
     * Query today's periods for teacher dashboard integration.
     */
    public function getTodayTeacherPeriods(User $user): array
    {
        $teacher = $user->teacher;
        if (!$teacher) {
            return [];
        }

        $session = AcademicSession::where('is_current', true)->first();
        if (!$session) {
            return [];
        }

        $today = today();
        $dayOfWeek = $today->dayOfWeekIso; // 1 (Mon) to 7 (Sun)

        $periods = Period::active()->ordered()->get();
        $firstPeriodId = $periods->where('is_break', false)->sortBy('start_time')->first()?->id;
        if ($periods->isEmpty()) {
            return [];
        }

        // Get published entries for this teacher today
        $entries = TimetableEntry::with(['schoolClass', 'section', 'subject', 'period'])
            ->where('session_id', $session->id)
            ->where('status', TimetableEntry::STATUS_PUBLISHED)
            ->where('day_of_week', $dayOfWeek)
            ->where(function ($query) use ($teacher, $today) {
                // Either regular teacher without substitute, or substitute teacher for today
                $query->where(function ($q) use ($teacher, $today) {
                    $q->where('teacher_id', $teacher->id)
                      ->whereDoesntHave('overrides', function ($oq) use ($today) {
                          $oq->where('date', $today->toDateString());
                      });
                })->orWhereHas('overrides', function ($oq) use ($teacher, $today) {
                    $oq->where('date', $today->toDateString())
                       ->where('type', TimetableOverride::TYPE_SUBSTITUTE)
                       ->where('substitute_teacher_id', $teacher->id);
                });
            })
            ->get();

        // If no timetable entries exist at all, return empty so dashboard shows fallback
        if ($entries->isEmpty()) {
            return [];
        }

        $result = [];
        foreach ($periods as $period) {
            $startsAt = $today->copy()->setTimeFromTimeString($period->start_time)->toIso8601String();
            $endsAt = $today->copy()->setTimeFromTimeString($period->end_time)->toIso8601String();

            if ($period->is_break) {
                $result[] = [
                    'assignment_id' => null,
                    'period_id' => $period->id,
                    'period_name' => $period->name,
                    'is_break' => true,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ];
                continue;
            }

            $entry = $entries->firstWhere('period_id', $period->id);

            if ($entry) {
                // Find matching teacher assignment
                $assignment = TeacherAssignment::where('session_id', $session->id)
                    ->where('class_id', $entry->class_id)
                    ->where('section_id', $entry->section_id)
                    ->where('subject_id', $entry->subject_id)
                    ->first();

                $result[] = [
                    'assignment_id' => $assignment?->id,
                    'entry_id' => $entry->id,
                    'period_id' => $period->id,
                    'period_name' => $period->name,
                    'is_break' => false,
                    'is_first_period' => $period->id === $firstPeriodId,
                    'class_name' => $entry->schoolClass->name,
                    'section_name' => $entry->section->name,
                    'subject_name' => $entry->subject->name,
                    'room' => $entry->room,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ];
            } else {
                // Free period
                $result[] = [
                    'assignment_id' => null,
                    'period_id' => $period->id,
                    'period_name' => $period->name,
                    'is_break' => false,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ];
            }
        }

        return $result;
    }
}

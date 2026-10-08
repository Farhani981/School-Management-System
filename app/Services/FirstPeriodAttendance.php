<?php

namespace App\Services;

use App\Models\Period;
use App\Models\TeacherAssignment;
use App\Models\TimetableEntry;
use App\Models\User;
use Carbon\CarbonImmutable;

class FirstPeriodAttendance
{
    public function allows(User $user, int $class, int $section, int $session, ?string $date = null): bool
    {
        if (!$user->hasRole('Teacher') || $user->teacher?->status !== 'Active') return false;
        if (!TeacherAssignment::where('teacher_id', $user->teacher->id)->where('class_id', $class)
            ->where('section_id', $section)->where('session_id', $session)->exists()) return false;

        $date = $date ?? today()->toDateString();
        $period = Period::active()->where('is_break', false)->orderBy('start_time')->orderBy('sort_order')->orderBy('id')->value('id');
        if (!$period) return false;

        return TimetableEntry::published()->where('class_id', $class)->where('section_id', $section)
            ->where('session_id', $session)->where('day_of_week', CarbonImmutable::parse($date)->dayOfWeekIso)
            ->where('period_id', $period)->where(function ($query) use ($user, $date) {
                $query->where(function ($regular) use ($user, $date) {
                    $regular->where('teacher_id', $user->teacher->id)
                        ->whereDoesntHave('overrides', fn ($override) => $override->whereDate('date', $date));
                })->orWhereHas('overrides', fn ($override) => $override->whereDate('date', $date)
                    ->whereIn('type', ['substitute', 'swapped'])->where('substitute_teacher_id', $user->teacher->id));
            })->exists();
    }
}

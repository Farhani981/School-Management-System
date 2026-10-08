<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\TimetableOverride;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TimetableOverrideController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->get('date', today()->toDateString());
        $carbonDate = Carbon::parse($date);
        $dayOfWeek = $carbonDate->dayOfWeekIso;

        $overrides = TimetableOverride::with(['entry.schoolClass', 'entry.section', 'entry.subject', 'entry.teacher', 'entry.period', 'substituteTeacher'])
            ->whereDate('date', $date)
            ->latest()
            ->paginate(20);

        $session = AcademicSession::where('is_current', true)->first() ?? AcademicSession::first();

        // Get all scheduled published entries for that day of week so admin can easily pick one to substitute
        $scheduledEntries = TimetableEntry::with(['schoolClass', 'section', 'subject', 'teacher', 'period'])
            ->where('session_id', $session?->id)
            ->where('status', TimetableEntry::STATUS_PUBLISHED)
            ->where('day_of_week', $dayOfWeek)
            ->get();

        $teachers = Teacher::where('status', 'Active')->orderBy('name')->get();

        return view('timetable.overrides', [
            'overrides' => $overrides,
            'selectedDate' => $date,
            'scheduledEntries' => $scheduledEntries,
            'teachers' => $teachers,
            'types' => TimetableOverride::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'entry_id' => 'required|exists:timetable_entries,id',
            'date' => 'required|date',
            'type' => 'required|in:substitute,cancelled,swapped',
            'substitute_teacher_id' => 'nullable|required_if:type,substitute|exists:teachers,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $entry = TimetableEntry::with('period')->findOrFail($validated['entry_id']);
        $date = $validated['date'];

        // If substitute teacher chosen, verify substitute is not the same teacher and not already busy
        if ($validated['type'] === TimetableOverride::TYPE_SUBSTITUTE && !empty($validated['substitute_teacher_id'])) {
            $substituteId = (int) $validated['substitute_teacher_id'];

            if ($substituteId === $entry->teacher_id) {
                throw ValidationException::withMessages([
                    'substitute_teacher_id' => 'The assigned teacher is already the primary teacher for this class.'
                ]);
            }

            // Check if substitute teacher already has another class during this period on this day
            $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;
            $busyWithRegularClass = TimetableEntry::where('session_id', $entry->session_id)
                ->where('teacher_id', $substituteId)
                ->where('day_of_week', $dayOfWeek)
                ->where('period_id', $entry->period_id)
                ->where('status', TimetableEntry::STATUS_PUBLISHED)
                ->exists();

            if ($busyWithRegularClass) {
                throw ValidationException::withMessages([
                    'substitute_teacher_id' => 'This substitute teacher is already scheduled to teach another class in this period.'
                ]);
            }
        }

        TimetableOverride::updateOrCreate(
            [
                'entry_id' => $entry->id,
                'date' => $date,
            ],
            [
                'type' => $validated['type'],
                'substitute_teacher_id' => $validated['substitute_teacher_id'] ?? null,
                'reason' => $validated['reason'] ?? null,
                'created_by' => $request->user()?->id,
            ]
        );

        return redirect()->route('timetable.overrides.index', ['date' => $date])
            ->with('success', 'Substitution/override recorded successfully.');
    }

    public function destroy(TimetableOverride $override)
    {
        $date = $override->date->toDateString();
        $override->delete();

        return redirect()->route('timetable.overrides.index', ['date' => $date])
            ->with('success', 'Override removed successfully.');
    }
}

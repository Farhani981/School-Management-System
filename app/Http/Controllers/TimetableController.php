<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\TimetableEntry;
use App\Services\TimetableService;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    public function index(Request $request)
    {
        $currentSession = AcademicSession::where('is_current', true)->first() ?? AcademicSession::first();
        $sessionId = (int) $request->get('session_id', $currentSession?->id);

        $classes = SchoolClass::orderBy('name')->get();
        $sections = SchoolSection::orderBy('name')->get();
        $sessions = AcademicSession::orderByDesc('is_current')->orderBy('name')->get();

        // Calculate stats per class & section
        $sectionsData = [];
        $totalEntries = 0;
        $publishedCount = 0;

        foreach ($classes as $class) {
            foreach ($sections as $section) {
                $entriesCount = TimetableEntry::where('session_id', $sessionId)
                    ->where('class_id', $class->id)
                    ->where('section_id', $section->id)
                    ->count();

                $isPublished = TimetableEntry::where('session_id', $sessionId)
                    ->where('class_id', $class->id)
                    ->where('section_id', $section->id)
                    ->where('status', TimetableEntry::STATUS_PUBLISHED)
                    ->exists();

                if ($isPublished) {
                    $publishedCount++;
                }
                $totalEntries += $entriesCount;

                $sectionsData[] = [
                    'class' => $class,
                    'section' => $section,
                    'entries_count' => $entriesCount,
                    'status' => $entriesCount === 0 ? 'Not Configured' : ($isPublished ? 'Published' : 'Draft'),
                    'is_published' => $isPublished,
                ];
            }
        }

        return view('timetable.index', [
            'classes' => $classes,
            'sessions' => $sessions,
            'selectedSession' => $sessionId,
            'sectionsData' => $sectionsData,
            'totalEntries' => $totalEntries,
            'publishedCount' => $publishedCount,
            'totalSections' => count($sectionsData),
        ]);
    }

    public function builder(Request $request, TimetableService $service)
    {
        if (! $request->user()->hasAnyRole(['Admin', 'Principal'])) {
            if ($request->user()->hasRole('Teacher')) {
                return redirect()->route('teacher.timetable.index');
            }
            abort(403, 'Only Admin and Principal accounts can manage the timetable.');
        }

        $currentSession = AcademicSession::where('is_current', true)->first() ?? AcademicSession::first();
        $display = $request->validate(['view' => 'nullable|in:daily,weekly', 'day' => 'nullable|integer|between:1,7']);
        $viewMode = $display['view'] ?? 'daily';
        $selectedDay = (int) ($display['day'] ?? 1);
        $sessionId = (int) $request->get('session_id', $currentSession?->id);

        $classes = SchoolClass::orderBy('name')->get();
        $sections = SchoolSection::orderBy('name')->get();
        $firstClass = $classes->first();
        $firstSection = $sections->first();

        $classId = (int) $request->get('class_id', $firstClass?->id);
        $sectionId = (int) $request->get('section_id', $firstSection?->id);

        $selectedClass = $classes->firstWhere('id', $classId) ?? $firstClass;
        $selectedSection = $sections->firstWhere('id', $sectionId) ?? $firstSection;

        $matrix = ['validAssignments' => collect()];
        if ($sessionId && $selectedClass && $selectedSection) {
            $matrix = $service->getSectionMatrix($sessionId, $selectedClass->id, $selectedSection->id);
            if ($viewMode === 'daily') $matrix['days'] = $matrix['days']->only([$selectedDay]);
        }

        return view('timetable.builder', array_merge([
            'classes' => $classes,
            'sections' => $sections,
            'sessions' => AcademicSession::orderByDesc('is_current')->orderBy('name')->get(),
            'selectedSessionId' => $sessionId,
            'selectedClass' => $selectedClass,
            'selectedSection' => $selectedSection,
            'viewMode' => $viewMode,
            'selectedDay' => $selectedDay,
        ], $matrix));
    }

    public function saveEntry(Request $request, TimetableService $service)
    {
        $validated = $request->validate([
            'session_id' => 'required|exists:academic_sessions,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:school_sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
            'day_of_week' => 'required|integer|between:1,7',
            'period_id' => 'required|exists:periods,id',
            'room' => 'nullable|string|max:50',
            'entry_id' => 'nullable|exists:timetable_entries,id',
        ]);

        $service->saveEntry($validated, $request->user()?->id);

        return redirect()->route('timetable.builder', [
            'session_id' => $validated['session_id'],
            'class_id' => $validated['class_id'],
            'section_id' => $validated['section_id'],
            'day' => $validated['day_of_week'],
        ])->with('success', 'Period slot allocated successfully.');
    }

    public function deleteEntry(TimetableEntry $entry)
    {
        $classId = $entry->class_id;
        $sectionId = $entry->section_id;
        $sessionId = $entry->session_id;

        $entry->delete();

        return redirect()->route('timetable.builder', [
            'session_id' => $sessionId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'day' => $entry->day_of_week,
        ])->with('success', 'Slot cleared successfully.');
    }

    public function repeatRoutine(Request $request, TimetableService $service)
    {
        abort_unless($request->user()->hasAnyRole(['Admin', 'Principal']), 403);
        $data = $request->validate(['session_id' => 'required|exists:academic_sessions,id',
            'class_id' => 'required|exists:school_classes,id', 'section_id' => 'required|exists:school_sections,id',
            'source_day' => 'required|integer|between:1,7', 'teaching_days' => 'required|integer|in:6,7']);
        $count = $service->repeatDailyRoutine($data, $request->user()->id);
        return redirect()->route('timetable.builder', ['session_id' => $data['session_id'], 'class_id' => $data['class_id'],
            'section_id' => $data['section_id'], 'day' => $data['source_day']])
            ->with('success', "Daily routine applied to {$data['teaching_days']} days ({$count} slots). Publish the timetable to activate it.");
    }

    public function teacherRoutine(Request $request)
    {
        $teachers = \App\Models\Teacher::where('status', 'Active')->orderBy('name')->get();
        $sessions = AcademicSession::orderByDesc('is_current')->get();
        $selection = $request->validate(['teacher_id' => 'nullable|integer|exists:teachers,id', 'session_id' => 'nullable|integer|exists:academic_sessions,id']);
        $teacherId = (int) ($selection['teacher_id'] ?? $teachers->first()?->id);
        $sessionId = (int) ($selection['session_id'] ?? $sessions->first()?->id);
        return view('timetable.teacher-routine', ['teachers' => $teachers, 'sessions' => $sessions,
            'teacherId' => $teacherId, 'sessionId' => $sessionId, 'periods' => \App\Models\Period::active()->ordered()->get(),
            'assignments' => \App\Models\TeacherAssignment::with(['schoolClass', 'section', 'subject'])->where('teacher_id', $teacherId)->where('session_id', $sessionId)->get(),
            'entries' => TimetableEntry::where('teacher_id', $teacherId)->where('session_id', $sessionId)->orderBy('day_of_week')->get()]);
    }

    public function saveTeacherRoutine(Request $request, TimetableService $service)
    {
        $data = $request->validate(['teacher_id' => 'required|integer|exists:teachers,id', 'session_id' => 'required|integer|exists:academic_sessions,id',
            'teaching_days' => 'required|integer|in:6,7', 'periods' => 'required|array', 'periods.*' => 'nullable|integer|exists:teacher_assignments,id']);
        $service->saveTeacherRoutine($data, $request->user()->id);
        return redirect()->route('timetable.teacher-routine', ['teacher_id' => $data['teacher_id'], 'session_id' => $data['session_id']])
            ->with('success', 'Daily teaching routine saved and published for the whole teaching week.');
    }

    public function publish(Request $request, TimetableService $service)
    {
        $request->validate([
            'session_id' => 'required|exists:academic_sessions,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:school_sections,id',
        ]);

        $updated = $service->setPublishStatus(
            (int) $request->session_id,
            (int) $request->class_id,
            (int) $request->section_id,
            true
        );

        return back()->with('success', "Timetable published! {$updated} slots are now visible to teachers.");
    }

    public function unpublish(Request $request, TimetableService $service)
    {
        $request->validate([
            'session_id' => 'required|exists:academic_sessions,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:school_sections,id',
        ]);

        $service->setPublishStatus(
            (int) $request->session_id,
            (int) $request->class_id,
            (int) $request->section_id,
            false
        );

        return back()->with('success', 'Timetable returned to draft status.');
    }
}

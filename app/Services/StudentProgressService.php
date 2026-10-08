<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\GradeScale;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentNote;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class StudentProgressService
{
    // Threshold Constants
    public const ATTENDANCE_AT_RISK_THRESHOLD = 75.0;
    public const ATTENDANCE_NEEDS_ATTENTION_THRESHOLD = 85.0;
    public const ACADEMIC_AT_RISK_THRESHOLD = 40.0;
    public const ACADEMIC_GAP_THRESHOLD = 15.0; // points below class average
    public const TREND_FLAT_DELTA = 2.0;

    /**
     * Get all unique class and section combinations assigned to this teacher.
     */
    public function getAssignedClassSections(Teacher $teacher): array
    {
        $assignments = TeacherAssignment::where('teacher_id', $teacher->id)
            ->with(['schoolClass', 'section'])
            ->get();

        $groups = [];
        foreach ($assignments as $a) {
            $key = $a->class_id . '-' . $a->section_id;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'class_id' => $a->class_id,
                    'section_id' => $a->section_id,
                    'class_name' => $a->schoolClass?->name ?? 'Class',
                    'section_name' => $a->section?->name ?? 'Section',
                    'session_id' => $a->session_id,
                    'display_name' => ($a->schoolClass?->name ?? 'Class') . ' · ' . ($a->section?->name ?? 'Section'),
                ];
            }
        }

        return array_values($groups);
    }

    /**
     * Verify if teacher is authorized to access the given student.
     */
    public function isTeacherAssignedToStudent(Teacher $teacher, Student $student): bool
    {
        return TeacherAssignment::where('teacher_id', $teacher->id)
            ->where('class_id', $student->class_id)
            ->where('section_id', $student->section_id)
            ->exists();
    }

    /**
     * Get students belonging to a class and section, sorted by roll number.
     */
    public function getRoster(int $classId, int $sectionId): Collection
    {
        return Student::where('status', 'Active')
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->orderByRaw('CAST(roll_number AS DECIMAL(12,2)) ASC')
            ->orderBy('id')
            ->get();
    }

    /**
     * Compute full individual student progress data.
     */
    public function getStudentProgressData(
        Student $student,
        ?int $sessionId = null,
        array $filters = []
    ): array {
        $sessionId = $sessionId ?: ($student->academic_session_id ?: AcademicSession::where('is_current', true)->value('id'));

        // 1. Fetch assessments for this student's class and section in this session
        $quizzesQuery = Quiz::whereNull('exam_subject_id')->where('class_id', $student->class_id)
            ->where('section_id', $student->section_id)
            ->where('status', '!=', 'draft');

        if ($sessionId) {
            $quizzesQuery->where('session_id', $sessionId);
        }

        // Apply filters
        if (! empty($filters['subject_id'])) {
            $quizzesQuery->where('subject_id', (int) $filters['subject_id']);
        }
        if (! empty($filters['type'])) {
            $quizzesQuery->where('type', $filters['type']);
        }
        if (! empty($filters['date_from'])) {
            $quizzesQuery->where('date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $quizzesQuery->where('date', '<=', $filters['date_to']);
        }
        if (! empty($filters['timeframe'])) {
            if ($filters['timeframe'] === 'last5') {
                $quizzesQuery->latest('date')->take(5);
            } elseif ($filters['timeframe'] === 'term') {
                $quizzesQuery->where('date', '>=', today()->subMonths(3));
            }
        }

        $allQuizzes = $quizzesQuery->with(['subject', 'results'])->orderBy('date', 'asc')->get();

        // 2. Class averages per quiz (cached in-memory for this computation)
        $quizClassAverages = [];
        foreach ($allQuizzes as $q) {
            $presentScores = $q->results->filter(fn ($r) => $r->attendance_status === 'present' && $r->marks_obtained !== null);
            if ($presentScores->isNotEmpty() && $q->total_marks > 0) {
                $avgMarks = $presentScores->avg('marks_obtained');
                $quizClassAverages[$q->id] = round(($avgMarks / $q->total_marks) * 100, 1);
            } else {
                $quizClassAverages[$q->id] = null;
            }
        }

        // 3. Student's results
        $results = QuizResult::where('student_id', $student->id)
            ->whereIn('quiz_id', $allQuizzes->pluck('id'))
            ->with(['quiz.subject'])
            ->get()
            ->keyBy('quiz_id');

        $assessmentRows = [];
        $presentResults = [];

        foreach ($allQuizzes as $quiz) {
            $res = $results->get($quiz->id);
            $classAvg = $quizClassAverages[$quiz->id] ?? null;

            if ($res) {
                $percentage = $res->percentage;
                $status = match ($res->attendance_status) {
                    'absent' => 'Absent',
                    'exempt' => 'Exempt',
                    default => ($res->is_passed ? 'Pass' : 'Fail'),
                };
                $grade = ($res->attendance_status === 'present' && $percentage !== null)
                    ? (GradeScale::forPercentage($percentage)?->grade ?? '–')
                    : '–';

                $row = [
                    'quiz_id' => $quiz->id,
                    'title' => $quiz->title,
                    'type' => Quiz::TYPES[$quiz->type] ?? ucfirst($quiz->type),
                    'raw_type' => $quiz->type,
                    'subject' => $quiz->subject?->name ?? 'General',
                    'subject_id' => $quiz->subject_id,
                    'date' => $quiz->date,
                    'marks_obtained' => $res->marks_obtained,
                    'total_marks' => $quiz->total_marks,
                    'score_display' => $res->attendance_status === 'present' && $res->marks_obtained !== null
                        ? ((float) $res->marks_obtained + 0) . ' / ' . ((float) $quiz->total_marks + 0)
                        : '–',
                    'percentage' => $res->attendance_status === 'present' ? $percentage : null,
                    'class_average' => $classAvg,
                    'grade' => $grade,
                    'attendance_status' => $res->attendance_status,
                    'status' => $status,
                    'remarks' => $res->remarks,
                ];

                $assessmentRows[] = $row;
                if ($res->attendance_status === 'present' && $percentage !== null) {
                    $presentResults[] = $row;
                }
            } else {
                // Not entered yet or exempt
                $assessmentRows[] = [
                    'quiz_id' => $quiz->id,
                    'title' => $quiz->title,
                    'type' => Quiz::TYPES[$quiz->type] ?? ucfirst($quiz->type),
                    'raw_type' => $quiz->type,
                    'subject' => $quiz->subject?->name ?? 'General',
                    'subject_id' => $quiz->subject_id,
                    'date' => $quiz->date,
                    'marks_obtained' => null,
                    'total_marks' => $quiz->total_marks,
                    'score_display' => '–',
                    'percentage' => null,
                    'class_average' => $classAvg,
                    'grade' => '–',
                    'attendance_status' => 'not_entered',
                    'status' => 'Unmarked',
                    'remarks' => null,
                ];
            }
        }

        // 4. Academic Summary Stats
        $assessmentsCount = count($presentResults);
        $academicAverage = $assessmentsCount > 0 ? round(collect($presentResults)->avg('percentage'), 1) : null;
        $passedCount = collect($presentResults)->filter(fn ($r) => $r['status'] === 'Pass')->count();

        // 5. Trend Calculation
        // Compare latest present assessment with the previous one
        $trend = ['direction' => 'flat', 'delta' => 0.0, 'label' => 'No prior assessment'];
        $consecutiveDrops = 0;

        if (count($presentResults) >= 2) {
            $latest = $presentResults[count($presentResults) - 1]['percentage'];
            $previous = $presentResults[count($presentResults) - 2]['percentage'];
            $delta = round($latest - $previous, 1);

            if ($delta >= self::TREND_FLAT_DELTA) {
                $trend = ['direction' => 'up', 'delta' => $delta, 'label' => "+{$delta}% vs previous"];
            } elseif ($delta <= -self::TREND_FLAT_DELTA) {
                $trend = ['direction' => 'down', 'delta' => abs($delta), 'label' => "-{abs($delta)}% vs previous"];
            } else {
                $trend = ['direction' => 'flat', 'delta' => abs($delta), 'label' => 'Stable (±2%)'];
            }

            // Check for 3 consecutive drops
            for ($i = count($presentResults) - 1; $i >= 1; $i--) {
                if ($presentResults[$i]['percentage'] < $presentResults[$i - 1]['percentage']) {
                    $consecutiveDrops++;
                } else {
                    break;
                }
            }
        }

        // 6. Class Rank (using all finalized assessments in the section)
        $rankData = $this->calculateClassRank($student, $sessionId);

        // 7. Attendance Stats
        $attendanceStats = $this->getStudentAttendanceStats($student, $sessionId);

        // 8. Overall Status & Reason
        $statusInfo = $this->determineStudentStatus(
            $attendanceStats['percentage'],
            $academicAverage,
            $rankData['class_average'],
            $consecutiveDrops,
            $assessmentsCount,
            $attendanceStats['total_marked']
        );

        // 9. Subject-wise breakdown (Student Avg vs Class Avg)
        $subjectBreakdown = $this->calculateSubjectComparison($student, $allQuizzes, $results);

        // 10. Notes & Follow-ups
        $notes = StudentNote::where('student_id', $student->id)
            ->where('session_id', $sessionId)
            ->with(['teacher', 'student'])
            ->latest('created_at')
            ->get();

        $overdueNotesCount = $notes->filter(fn ($n) => $n->isOverdue())->count();

        // 11. Previous & Next student in the section
        $nav = $this->getStudentNavigation($student);

        return [
            'student' => $student,
            'sessionId' => $sessionId,
            'summary' => [
                'assessments_count' => $assessmentsCount,
                'academic_average' => $academicAverage,
                'passed_count' => $passedCount,
                'passed_total' => $assessmentsCount,
                'attendance_pct' => $attendanceStats['percentage'],
                'class_rank' => $rankData['rank_display'],
                'rank_number' => $rankData['rank'],
                'total_students' => $rankData['total_students'],
                'trend' => $trend,
            ],
            'status' => $statusInfo,
            'attendance' => $attendanceStats,
            'trend_chart' => [
                'points' => $presentResults,
                'subject_breakdown' => $subjectBreakdown,
            ],
            'assessment_history' => array_reverse($assessmentRows), // Newest first
            'notes' => $notes,
            'overdue_notes_count' => $overdueNotesCount,
            'nav' => $nav,
        ];
    }

    /**
     * Determine status: At risk, Needs attention, On track, or Not enough data.
     */
    public function determineStudentStatus(
        ?float $attendancePct,
        ?float $academicAvg,
        ?float $classAvg,
        int $consecutiveDrops,
        int $assessmentsCount,
        int $attendanceMarkedCount
    ): array {
        if ($assessmentsCount === 0 && $attendanceMarkedCount === 0) {
            return [
                'state' => 'no_data',
                'label' => 'Not enough data',
                'badge_class' => 'status-neutral',
                'reason' => 'No assessments or attendance marked yet for this session.',
            ];
        }

        // At risk: attendance < 75% OR academic average < 40%
        if ($attendancePct !== null && $attendancePct < self::ATTENDANCE_AT_RISK_THRESHOLD) {
            return [
                'state' => 'at_risk',
                'label' => 'At risk',
                'badge_class' => 'status-danger',
                'reason' => "Attendance is {$attendancePct}%, below the 75% minimum threshold.",
            ];
        }

        if ($academicAvg !== null && $academicAvg < self::ACADEMIC_AT_RISK_THRESHOLD) {
            return [
                'state' => 'at_risk',
                'label' => 'At risk',
                'badge_class' => 'status-danger',
                'reason' => "Academic average is {$academicAvg}%, below the 40% passing standard.",
            ];
        }

        // Needs attention: attendance < 85% OR avg >= 15 pts below class average OR 3 consecutive drops
        if ($attendancePct !== null && $attendancePct < self::ATTENDANCE_NEEDS_ATTENTION_THRESHOLD) {
            return [
                'state' => 'needs_attention',
                'label' => 'Needs attention',
                'badge_class' => 'status-warn',
                'reason' => "Attendance is {$attendancePct}%, below recommended 85%.",
            ];
        }

        if ($academicAvg !== null && $classAvg !== null && ($classAvg - $academicAvg) >= self::ACADEMIC_GAP_THRESHOLD) {
            $gap = round($classAvg - $academicAvg, 1);
            return [
                'state' => 'needs_attention',
                'label' => 'Needs attention',
                'badge_class' => 'status-warn',
                'reason' => "Academic average ({$academicAvg}%) is {$gap} points below class average ({$classAvg}%).",
            ];
        }

        if ($consecutiveDrops >= 3) {
            return [
                'state' => 'needs_attention',
                'label' => 'Needs attention',
                'badge_class' => 'status-warn',
                'reason' => "Student has experienced {$consecutiveDrops} consecutive score drops.",
            ];
        }

        // Otherwise: On track
        return [
            'state' => 'on_track',
            'label' => 'On track',
            'badge_class' => 'status-ok',
            'reason' => 'Performance and attendance are both in good standing.',
        ];
    }

    /**
     * Calculate class rank (using finalized assessments). Ties share a rank.
     */
    public function calculateClassRank(Student $student, ?int $sessionId): array
    {
        $classmates = Student::where('status', 'Active')
            ->where('class_id', $student->class_id)
            ->where('section_id', $student->section_id)
            ->pluck('id');

        if ($classmates->isEmpty()) {
            return ['rank' => null, 'total_students' => 0, 'rank_display' => '–', 'class_average' => null];
        }

        $quizzesQuery = Quiz::whereNull('exam_subject_id')->where('class_id', $student->class_id)
            ->where('section_id', $student->section_id)
            ->where('status', '!=', 'draft');

        if ($sessionId) {
            $quizzesQuery->where('session_id', $sessionId);
        }

        $quizIds = $quizzesQuery->pluck('id');
        if ($quizIds->isEmpty()) {
            return ['rank' => null, 'total_students' => $classmates->count(), 'rank_display' => '–', 'class_average' => null];
        }

        // Compute average percentage for each student
        $results = QuizResult::whereIn('student_id', $classmates)
            ->whereIn('quiz_id', $quizIds)
            ->where('attendance_status', 'present')
            ->whereNotNull('marks_obtained')
            ->with('quiz')
            ->get();

        $studentAverages = [];
        foreach ($classmates as $id) {
            $studentResults = $results->where('student_id', $id);
            if ($studentResults->isNotEmpty()) {
                $pcts = $studentResults->map(fn ($r) => $r->percentage)->filter(fn ($p) => $p !== null);
                if ($pcts->isNotEmpty()) {
                    $studentAverages[$id] = round($pcts->avg(), 2);
                }
            }
        }

        if (empty($studentAverages) || ! isset($studentAverages[$student->id])) {
            $overallClassAvg = count($studentAverages) > 0 ? round(collect($studentAverages)->avg(), 1) : null;
            return ['rank' => null, 'total_students' => $classmates->count(), 'rank_display' => '–', 'class_average' => $overallClassAvg];
        }

        $myAvg = $studentAverages[$student->id];
        // Standard competition ranking: 1 + number of students strictly higher
        $higherCount = collect($studentAverages)->filter(fn ($avg) => $avg > $myAvg)->count();
        $rank = $higherCount + 1;
        $totalRanked = count($studentAverages);
        $overallClassAvg = round(collect($studentAverages)->avg(), 1);

        return [
            'rank' => $rank,
            'total_students' => $classmates->count(),
            'total_ranked' => $totalRanked,
            'rank_display' => "{$rank} of {$classmates->count()}",
            'class_average' => $overallClassAvg,
        ];
    }

    /**
     * Compute attendance breakdown for student.
     */
    public function getStudentAttendanceStats(Student $student, ?int $sessionId): array
    {
        $query = StudentAttendance::where('student_id', $student->id);
        if ($sessionId) {
            $query->where('academic_session_id', $sessionId);
        }

        $records = $query->orderBy('date', 'desc')->get();

        $presentCount = $records->whereIn('status', ['present', 'Present'])->count();
        $lateCount = $records->whereIn('status', ['late', 'Late'])->count();
        $absentCount = $records->whereIn('status', ['absent', 'Absent'])->count();
        $leaveCount = $records->whereIn('status', ['leave', 'Leave'])->count();

        $totalMarked = $records->count();
        $attendedDays = $presentCount + $lateCount; // Present and Late count as attendance

        $percentage = $totalMarked > 0 ? round(($attendedDays / $totalMarked) * 100, 1) : null;

        // Last 5 absent dates
        $recentAbsents = $records->whereIn('status', ['absent', 'Absent'])
            ->take(5)
            ->map(fn ($r) => [
                'date' => $r->date,
                'remarks' => $r->remarks ?: 'Absent',
            ])
            ->values()
            ->all();

        // Monthly breakdown
        $monthly = [];
        foreach ($records as $r) {
            if ($r->date) {
                $monthKey = \Carbon\Carbon::parse($r->date)->format('M Y');
                if (! isset($monthly[$monthKey])) {
                    $monthly[$monthKey] = ['present' => 0, 'total' => 0, 'month' => $monthKey];
                }
                $monthly[$monthKey]['total']++;
                if (in_array(strtolower($r->status), ['present', 'late'])) {
                    $monthly[$monthKey]['present']++;
                }
            }
        }

        $monthlyData = [];
        foreach ($monthly as $key => $m) {
            $pct = $m['total'] > 0 ? round(($m['present'] / $m['total']) * 100, 1) : 0;
            $monthlyData[] = [
                'month' => $key,
                'percentage' => $pct,
                'is_low' => $pct < self::ATTENDANCE_AT_RISK_THRESHOLD,
                'present' => $m['present'],
                'total' => $m['total'],
            ];
        }

        return [
            'percentage' => $percentage,
            'is_low' => $percentage !== null && $percentage < self::ATTENDANCE_AT_RISK_THRESHOLD,
            'present' => $presentCount,
            'late' => $lateCount,
            'absent' => $absentCount,
            'leave' => $leaveCount,
            'total_marked' => $totalMarked,
            'recent_absents' => $recentAbsents,
            'monthly' => array_reverse($monthlyData),
        ];
    }

    /**
     * Compute subject comparison: student average vs class average per subject.
     */
    public function calculateSubjectComparison(Student $student, Collection $allQuizzes, Collection $studentResults): array
    {
        $subjects = Subject::whereIn('id', $allQuizzes->pluck('subject_id')->unique())->get()->keyBy('id');
        $comparison = [];

        foreach ($subjects as $subId => $sub) {
            $subQuizzes = $allQuizzes->where('subject_id', $subId);
            $studentPcts = [];
            $allClassPcts = [];

            foreach ($subQuizzes as $q) {
                // Student's score
                $res = $studentResults->get($q->id);
                if ($res && $res->attendance_status === 'present' && $res->percentage !== null) {
                    $studentPcts[] = $res->percentage;
                }

                // Class scores
                $presentScores = $q->results->filter(fn ($r) => $r->attendance_status === 'present' && $r->marks_obtained !== null);
                if ($presentScores->isNotEmpty() && $q->total_marks > 0) {
                    foreach ($presentScores as $ps) {
                        $allClassPcts[] = ($ps->marks_obtained / $q->total_marks) * 100;
                    }
                }
            }

            if (! empty($studentPcts) || ! empty($allClassPcts)) {
                $studentAvg = ! empty($studentPcts) ? round(collect($studentPcts)->avg(), 1) : null;
                $classAvg = ! empty($allClassPcts) ? round(collect($allClassPcts)->avg(), 1) : null;

                $comparison[] = [
                    'subject_id' => $subId,
                    'subject_name' => $sub->name,
                    'student_avg' => $studentAvg,
                    'class_avg' => $classAvg,
                    'assessments_count' => count($studentPcts),
                ];
            }
        }

        // Strongest & Weakest subject text
        $rankedSubjects = collect($comparison)->filter(fn ($c) => $c['student_avg'] !== null)->sortByDesc('student_avg')->values();
        $strongest = $rankedSubjects->first();
        $weakest = $rankedSubjects->last();

        $highlightText = '';
        if ($strongest && $weakest && $strongest['subject_id'] !== $weakest['subject_id']) {
            $highlightText = "Strongest: {$strongest['subject_name']} {$strongest['student_avg']}%. Needs improvement: {$weakest['subject_name']} {$weakest['student_avg']}%.";
        } elseif ($strongest) {
            $highlightText = "Average performance across assessments: {$strongest['student_avg']}%.";
        }

        return [
            'items' => $comparison,
            'highlight_text' => $highlightText,
            'strongest' => $strongest,
            'weakest' => $weakest,
        ];
    }

    /**
     * Get previous and next students in section by roll number.
     */
    public function getStudentNavigation(Student $student): array
    {
        $roster = $this->getRoster($student->class_id, $student->section_id);
        $currentIndex = $roster->search(fn ($s) => $s->id === $student->id);

        $prev = ($currentIndex !== false && $currentIndex > 0) ? $roster->get($currentIndex - 1) : null;
        $next = ($currentIndex !== false && $currentIndex < $roster->count() - 1) ? $roster->get($currentIndex + 1) : null;

        return [
            'prev' => $prev,
            'next' => $next,
            'current_index' => $currentIndex !== false ? $currentIndex + 1 : 1,
            'total_students' => $roster->count(),
        ];
    }

    /**
     * Compute Class Overview data:
     * - Class stats (average, pass %, attendance average, assessments count)
     * - Needs attention list
     * - Top 5 and Bottom 5 students
     * - Heatmap: students x subjects
     * - Comparison data for up to 3 student IDs
     */
    public function getClassOverviewData(int $classId, int $sectionId, ?int $sessionId = null, array $compareStudentIds = []): array
    {
        $sessionId = $sessionId ?: AcademicSession::where('is_current', true)->value('id');

        $schoolClass = SchoolClass::find($classId);
        $section = SchoolSection::find($sectionId);
        $roster = $this->getRoster($classId, $sectionId);

        $quizzes = Quiz::whereNull('exam_subject_id')->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('status', '!=', 'draft')
            ->when($sessionId, fn ($q) => $q->where('session_id', $sessionId))
            ->with(['subject', 'results'])
            ->get();

        // 1. Overall Class Stats
        $allPresentResults = [];
        foreach ($quizzes as $q) {
            foreach ($q->results as $r) {
                if ($r->attendance_status === 'present' && $r->marks_obtained !== null && $q->total_marks > 0) {
                    $allPresentResults[] = [
                        'percentage' => ($r->marks_obtained / $q->total_marks) * 100,
                        'is_passed' => $r->marks_obtained >= $q->passing_marks,
                    ];
                }
            }
        }

        $classAverage = ! empty($allPresentResults) ? round(collect($allPresentResults)->avg('percentage'), 1) : null;
        $totalPresentCount = count($allPresentResults);
        $passCount = collect($allPresentResults)->filter(fn ($r) => $r['is_passed'])->count();
        $classPassPct = $totalPresentCount > 0 ? round(($passCount / $totalPresentCount) * 100, 1) : null;

        // Class Attendance average
        $attendanceQuery = StudentAttendance::where('school_class_id', $classId)
            ->where('section_id', $sectionId);
        if ($sessionId) {
            $attendanceQuery->where('academic_session_id', $sessionId);
        }
        $attendanceRecords = $attendanceQuery->get();
        $presentAttCount = $attendanceRecords->whereIn('status', ['present', 'late', 'Present', 'Late'])->count();
        $classAttendanceAvg = $attendanceRecords->isNotEmpty() ? round(($presentAttCount / $attendanceRecords->count()) * 100, 1) : null;

        // 2. Individual student aggregates for this class
        $subjects = Subject::whereIn('id', $quizzes->pluck('subject_id')->unique())->get();
        $studentAverages = [];
        $studentAttendancePcts = [];
        $studentStatusList = [];

        foreach ($roster as $st) {
            // Student results
            $stResults = [];
            foreach ($quizzes as $q) {
                $r = $q->results->firstWhere('student_id', $st->id);
                if ($r && $r->attendance_status === 'present' && $r->marks_obtained !== null && $q->total_marks > 0) {
                    $stResults[] = ($r->marks_obtained / $q->total_marks) * 100;
                }
            }

            $avg = ! empty($stResults) ? round(collect($stResults)->avg(), 1) : null;
            $studentAverages[$st->id] = $avg;

            // Student attendance
            $stAtt = $attendanceRecords->where('student_id', $st->id);
            $stAttPresent = $stAtt->whereIn('status', ['present', 'late', 'Present', 'Late'])->count();
            $attPct = $stAtt->isNotEmpty() ? round(($stAttPresent / $stAtt->count()) * 100, 1) : null;
            $studentAttendancePcts[$st->id] = $attPct;

            // Determine status
            $statusInfo = $this->determineStudentStatus(
                $attPct,
                $avg,
                $classAverage,
                0, // drops not needed for summary list
                count($stResults),
                $stAtt->count()
            );

            $studentStatusList[$st->id] = $statusInfo;
        }

        // 3. "Needs Attention" List (Sorted by severity)
        $needsAttentionList = [];
        foreach ($roster as $st) {
            $status = $studentStatusList[$st->id];
            if (in_array($status['state'], ['at_risk', 'needs_attention'])) {
                $severityScore = ($status['state'] === 'at_risk' ? 200 : 100)
                    + (100 - ($studentAverages[$st->id] ?? 50))
                    + (100 - ($studentAttendancePcts[$st->id] ?? 50));

                $needsAttentionList[] = [
                    'student' => $st,
                    'status' => $status,
                    'average' => $studentAverages[$st->id],
                    'attendance' => $studentAttendancePcts[$st->id],
                    'severity' => $severityScore,
                ];
            }
        }

        usort($needsAttentionList, fn ($a, $b) => $b['severity'] <=> $a['severity']);

        // 4. Top 5 and Bottom 5 students by academic average
        $rankedRoster = $roster->filter(fn ($s) => $studentAverages[$s->id] !== null)
            ->sortByDesc(fn ($s) => $studentAverages[$s->id])
            ->values();

        $top5 = $rankedRoster->take(5)->map(fn ($s) => [
            'student' => $s,
            'average' => $studentAverages[$s->id],
            'attendance' => $studentAttendancePcts[$s->id],
        ])->all();

        $bottom5 = $rankedRoster->reverse()->take(5)->map(fn ($s) => [
            'student' => $s,
            'average' => $studentAverages[$s->id],
            'attendance' => $studentAttendancePcts[$s->id],
        ])->all();

        // 5. Heatmap matrix: Students (rows) x Subjects (columns)
        $heatmap = [];
        foreach ($roster as $st) {
            $rowCells = [];
            foreach ($subjects as $sub) {
                $subQuizzes = $quizzes->where('subject_id', $sub->id);
                $pcts = [];
                foreach ($subQuizzes as $q) {
                    $r = $q->results->firstWhere('student_id', $st->id);
                    if ($r && $r->attendance_status === 'present' && $r->marks_obtained !== null && $q->total_marks > 0) {
                        $pcts[] = ($r->marks_obtained / $q->total_marks) * 100;
                    }
                }
                $cellAvg = ! empty($pcts) ? round(collect($pcts)->avg(), 1) : null;
                $rowCells[$sub->id] = [
                    'subject_id' => $sub->id,
                    'average' => $cellAvg,
                    'color_class' => $cellAvg !== null ? ($cellAvg < 60 ? 'heat-low' : ($cellAvg < 75 ? 'heat-mid' : 'heat-high')) : 'heat-empty',
                ];
            }
            $heatmap[] = [
                'student' => $st,
                'overall_average' => $studentAverages[$st->id],
                'cells' => $rowCells,
            ];
        }

        // 6. Comparison Data (up to 3 students)
        $comparisonData = [];
        $compareIds = array_slice(array_filter($compareStudentIds), 0, 3);
        if (! empty($compareIds)) {
            $compareStudents = $roster->whereIn('id', $compareIds);
            foreach ($compareStudents as $cs) {
                $subScores = [];
                foreach ($subjects as $sub) {
                    $subQuizzes = $quizzes->where('subject_id', $sub->id);
                    $pcts = [];
                    foreach ($subQuizzes as $q) {
                        $r = $q->results->firstWhere('student_id', $cs->id);
                        if ($r && $r->attendance_status === 'present' && $r->marks_obtained !== null && $q->total_marks > 0) {
                            $pcts[] = ($r->marks_obtained / $q->total_marks) * 100;
                        }
                    }
                    $subScores[$sub->name] = ! empty($pcts) ? round(collect($pcts)->avg(), 1) : 0;
                }
                $comparisonData[] = [
                    'student' => $cs,
                    'average' => $studentAverages[$cs->id] ?? 0,
                    'attendance' => $studentAttendancePcts[$cs->id] ?? 0,
                    'subject_scores' => $subScores,
                ];
            }
        }

        return [
            'schoolClass' => $schoolClass,
            'section' => $section,
            'roster' => $roster,
            'subjects' => $subjects,
            'stats' => [
                'class_average' => $classAverage,
                'pass_percentage' => $classPassPct,
                'attendance_average' => $classAttendanceAvg,
                'assessments_count' => $quizzes->count(),
                'total_students' => $roster->count(),
            ],
            'needs_attention' => $needsAttentionList,
            'top5' => $top5,
            'bottom5' => $bottom5,
            'heatmap' => $heatmap,
            'comparison' => $comparisonData,
            'compare_ids' => $compareIds,
        ];
    }
}

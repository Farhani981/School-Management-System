<?php

namespace App\Services;

use App\Models\GradeScale;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\QuizResultAudit;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizService
{
    /**
     * Get all active teaching assignments for a teacher.
     */
    public function getTeacherAssignments(Teacher $teacher)
    {
        return TeacherAssignment::where('teacher_id', $teacher->id)
            ->with(['schoolClass', 'section', 'subject', 'session'])
            ->orderBy('class_id')
            ->orderBy('section_id')
            ->get();
    }

    /**
     * Get filtered, paginated list of quizzes for a teacher.
     */
    public function getFilteredQuizzes(Teacher $teacher, array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = Quiz::where('teacher_id', $teacher->id)->whereNull('exam_subject_id')
            ->with(['schoolClass', 'section', 'subject', 'session', 'results']);

        if (! empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (! empty($filters['section_id'])) {
            $query->where('section_id', $filters['section_id']);
        }

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('subject', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['status'])) {
            $status = $filters['status'];
            if ($status === 'draft') {
                $query->where('status', 'draft');
            } elseif ($status === 'closed') {
                $query->where('status', 'closed');
            }
            // 'pending' and 'completed' require checking roster vs entered marks
        }

        $allQuizzes = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->get();

        // If filtering by computed status ('pending' or 'completed'), filter the collection
        if (! empty($filters['status']) && in_array($filters['status'], ['pending', 'completed'])) {
            $target = $filters['status'];
            $allQuizzes = $allQuizzes->filter(fn (Quiz $q) => $q->computedStatus() === $target)->values();
        }

        $page = request()->get('page', 1);
        $offset = ($page - 1) * $perPage;
        $items = $allQuizzes->slice($offset, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $allQuizzes->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Count of published quizzes with missing marks for this teacher.
     */
    public function getPendingMarksCount(Teacher $teacher): int
    {
        return Cache::remember("teacher-quiz:pending-count:{$teacher->id}", 15, function () use ($teacher) {
            $quizzes = Quiz::where('teacher_id', $teacher->id)->whereNull('exam_subject_id')
                ->where('status', 'published')
                ->get();

            return $quizzes->filter(fn (Quiz $quiz) => $quiz->computedStatus() === 'pending')->count();
        });
    }

    /**
     * Get the active student roster for a quiz merged with any entered results.
     */
    public function getRosterForQuiz(Quiz $quiz)
    {
        $students = $quiz->rosterQuery()->get();
        $results = $quiz->results()->get()->keyBy('student_id');

        return $students->map(function (Student $student) use ($results) {
            $res = $results->get($student->id);
            return [
                'student' => $student,
                'result' => $res,
                'marks_obtained' => $res?->marks_obtained,
                'attendance_status' => $res?->attendance_status ?? 'present',
                'remarks' => $res?->remarks,
            ];
        });
    }

    /**
     * Save marks (Draft or Finalize) with database transactions and audit logging.
     */
    public function saveMarks(Quiz $quiz, array $rows, User $user, ?string $reason = null, bool $finalize = false): void
    {
        DB::transaction(function () use ($quiz, $rows, $user, $reason, $finalize) {
            $roster = $quiz->rosterQuery()->get()->keyBy('id');
            $existingResults = $quiz->results()->get()->keyBy('student_id');
            $wasFinalized = $quiz->status === 'published' && $quiz->computedStatus() === 'completed';

            // Validate post-finalize edit reason
            if ($wasFinalized && empty(trim($reason ?? ''))) {
                throw ValidationException::withMessages([
                    'reason' => 'A valid reason is required when modifying marks of a finalized assessment.',
                ]);
            }

            $missingCount = 0;

            foreach ($rows as $row) {
                $studentId = (int) ($row['student_id'] ?? 0);
                if (! $roster->has($studentId)) {
                    continue; // Skip any students not in current active roster
                }

                $attendanceStatus = strtolower(trim($row['attendance_status'] ?? 'present'));
                if (! in_array($attendanceStatus, ['present', 'absent', 'exempt'])) {
                    $attendanceStatus = 'present';
                }

                $rawMarks = $row['marks_obtained'] ?? null;
                $marks = null;

                if ($attendanceStatus === 'present') {
                    if ($rawMarks !== null && $rawMarks !== '') {
                        $marks = round((float) $rawMarks, 2);
                        if ($marks < 0 || $marks > (float) $quiz->total_marks) {
                            throw ValidationException::withMessages([
                                "marks_{$studentId}" => "Marks for student #{$roster[$studentId]->roll_number} must be between 0 and {$quiz->total_marks}.",
                            ]);
                        }
                    } else {
                        $missingCount++;
                    }
                }

                $remarks = ! empty($row['remarks']) ? trim($row['remarks']) : null;
                $existing = $existingResults->get($studentId);

                // Audit log if values changed on an already-finalized quiz
                if ($wasFinalized && $existing) {
                    $marksChanged = ((string) $existing->marks_obtained !== (string) $marks);
                    $statusChanged = ($existing->attendance_status !== $attendanceStatus);

                    if ($marksChanged || $statusChanged) {
                        QuizResultAudit::create([
                            'quiz_result_id' => $existing->id,
                            'quiz_id' => $quiz->id,
                            'student_id' => $studentId,
                            'old_marks' => $existing->marks_obtained,
                            'new_marks' => $marks,
                            'old_attendance' => $existing->attendance_status,
                            'new_attendance' => $attendanceStatus,
                            'reason' => trim($reason),
                            'changed_by' => $user->id,
                            'created_at' => now(),
                        ]);
                    }
                }

                QuizResult::updateOrCreate(
                    [
                        'quiz_id' => $quiz->id,
                        'student_id' => $studentId,
                    ],
                    [
                        'marks_obtained' => $marks,
                        'attendance_status' => $attendanceStatus,
                        'remarks' => $remarks,
                        'entered_by' => $user->id,
                        'entered_at' => now(),
                    ]
                );
            }

            // Finalize check: every student must have either marks or absent/exempt
            if ($finalize) {
                $totalActive = $roster->count();
                $completedCount = QuizResult::where('quiz_id', $quiz->id)
                    ->where(function ($q) {
                        $q->whereNotNull('marks_obtained')
                          ->orWhereIn('attendance_status', ['absent', 'exempt']);
                    })
                    ->count();

                if ($completedCount < $totalActive) {
                    $unmarked = $totalActive - $completedCount;
                    throw ValidationException::withMessages([
                        'finalize' => "Cannot finalize assessment: {$unmarked} " . ($unmarked === 1 ? 'student is' : 'students are') . " still missing marks or attendance status.",
                    ]);
                }

                $quiz->update([
                    'status' => 'published',
                    'published_at' => $quiz->published_at ?? now(),
                ]);
            }

            // Clear cache
            Cache::forget("teacher-quiz:pending-count:{$quiz->teacher_id}");
            Cache::forget(TeacherPanelService::cacheKey($user));
        });
    }

    /**
     * Compute full statistics for a quiz result page.
     * Business rule: Absent and Exempt students are excluded from average, highest, lowest, rank, and pass percentage.
     */
    public function calculateStatistics(Quiz $quiz): array
    {
        $rosterCount = $quiz->activeStudentsCount();
        $results = $quiz->results()->with('student')->get();

        $presentResults = $results->where('attendance_status', 'present')->whereNotNull('marks_obtained');
        $absentCount = $results->where('attendance_status', 'absent')->count();
        $exemptCount = $results->where('attendance_status', 'exempt')->count();
        $appearedCount = $presentResults->count();
        $unmarkedCount = max(0, $rosterCount - ($appearedCount + $absentCount + $exemptCount));

        $marksList = $presentResults->pluck('marks_obtained')->map(fn ($m) => (float) $m);

        $highest = $marksList->isNotEmpty() ? $marksList->max() : 0.0;
        $lowest = $marksList->isNotEmpty() ? $marksList->min() : 0.0;
        $averageMarks = $marksList->isNotEmpty() ? round($marksList->avg(), 2) : 0.0;
        $totalMarks = (float) $quiz->total_marks;
        $averagePercentage = $totalMarks > 0 ? round(($averageMarks / $totalMarks) * 100, 2) : 0.0;

        $passingMarks = (float) $quiz->passing_marks;
        $passedCount = $marksList->filter(fn ($m) => $m >= $passingMarks)->count();
        $failedCount = $appearedCount - $passedCount;
        $passPercentage = $appearedCount > 0 ? round(($passedCount / $appearedCount) * 100, 1) : 0.0;

        // Marks distribution buckets (0-39%, 40-49%, 50-59%, 60-69%, 70-79%, 80-89%, 90-100%)
        $buckets = [
            '0-39' => 0,
            '40-49' => 0,
            '50-59' => 0,
            '60-69' => 0,
            '70-79' => 0,
            '80-89' => 0,
            '90-100' => 0,
        ];

        foreach ($marksList as $mark) {
            $pct = $totalMarks > 0 ? ($mark / $totalMarks) * 100 : 0;
            if ($pct < 40) {
                $buckets['0-39']++;
            } elseif ($pct < 50) {
                $buckets['40-49']++;
            } elseif ($pct < 60) {
                $buckets['50-59']++;
            } elseif ($pct < 70) {
                $buckets['60-69']++;
            } elseif ($pct < 80) {
                $buckets['70-79']++;
            } elseif ($pct < 90) {
                $buckets['80-89']++;
            } else {
                $buckets['90-100']++;
            }
        }

        return [
            'total_students' => $rosterCount,
            'appeared_count' => $appearedCount,
            'absent_count' => $absentCount,
            'exempt_count' => $exemptCount,
            'unmarked_count' => $unmarkedCount,
            'average_marks' => $averageMarks,
            'average_percentage' => $averagePercentage,
            'highest_mark' => $highest,
            'lowest_mark' => $lowest,
            'passed_count' => $passedCount,
            'failed_count' => $failedCount,
            'pass_percentage' => $passPercentage,
            'distribution' => $buckets,
        ];
    }

    /**
     * Compute ranked student rows with standard competition ranking.
     * Ties share the same rank. Absent and exempt students have no rank.
     */
    public function calculateRanks(Quiz $quiz): array
    {
        $results = $quiz->results()->with('student')->get();
        $totalMarks = (float) $quiz->total_marks;
        $passingMarks = (float) $quiz->passing_marks;

        // Separate present students with marks from absent/exempt/unmarked
        $present = $results->filter(fn ($r) => $r->attendance_status === 'present' && $r->marks_obtained !== null)
            ->sortByDesc(fn ($r) => (float) $r->marks_obtained)
            ->values();

        $other = $results->filter(fn ($r) => $r->attendance_status !== 'present' || $r->marks_obtained === null)
            ->sortBy(fn ($r) => $r->student?->roll_number)
            ->values();

        $ranked = [];
        $currentRank = 1;
        $prevMark = null;
        $accumulated = 0;

        foreach ($present as $idx => $res) {
            $mark = (float) $res->marks_obtained;
            $accumulated++;

            if ($prevMark !== null && $mark < $prevMark) {
                $currentRank = $accumulated;
            }

            $percentage = $totalMarks > 0 ? round(($mark / $totalMarks) * 100, 2) : 0.0;
            $gradeScale = GradeScale::forPercentage($percentage);

            $ranked[] = [
                'rank' => $currentRank,
                'student' => $res->student,
                'result' => $res,
                'marks' => $mark,
                'percentage' => $percentage,
                'grade' => $gradeScale?->grade ?? '—',
                'remark' => $gradeScale?->remark ?? '',
                'passed' => $mark >= $passingMarks,
                'status' => 'present',
                'remarks' => $res->remarks,
            ];

            $prevMark = $mark;
        }

        // Add absent and exempt students with no rank
        foreach ($other as $res) {
            $ranked[] = [
                'rank' => '—',
                'student' => $res->student,
                'result' => $res,
                'marks' => null,
                'percentage' => null,
                'grade' => $res->attendance_status === 'absent' ? 'ABS' : ($res->attendance_status === 'exempt' ? 'EXM' : '—'),
                'remark' => ucfirst($res->attendance_status),
                'passed' => null,
                'status' => $res->attendance_status,
                'remarks' => $res->remarks,
            ];
        }

        return $ranked;
    }

    /**
     * Get students who failed or are significantly below the class average.
     */
    public function getNeedsAttention(Quiz $quiz, array $rankedStudents, array $stats): array
    {
        $avgMarks = (float) ($stats['average_marks'] ?? 0);
        $threshold = max(0, $avgMarks - 5);

        return collect($rankedStudents)->filter(function ($item) use ($quiz, $threshold) {
            if ($item['status'] !== 'present' || $item['marks'] === null) {
                return false;
            }
            // Failed OR significantly below class average
            return (! $item['passed']) || ($item['marks'] < $threshold);
        })->values()->all();
    }

    /**
     * Duplicate a quiz as a fresh Draft copy without copying marks.
     */
    public function duplicateQuiz(Quiz $quiz, Teacher $teacher): Quiz
    {
        return Quiz::create([
            'title' => "Copy of {$quiz->title}",
            'type' => $quiz->type,
            'class_id' => $quiz->class_id,
            'section_id' => $quiz->section_id,
            'subject_id' => $quiz->subject_id,
            'teacher_id' => $teacher->id,
            'session_id' => $quiz->session_id,
            'date' => today(),
            'total_marks' => $quiz->total_marks,
            'passing_marks' => $quiz->passing_marks,
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    /**
     * Parse CSV for marks import.
     * Expects CSV headers with roll_no (or admission_no) and marks.
     */
    public function parseCsvImport(Quiz $quiz, string $csvContent): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'csv_file' => 'The uploaded CSV file is empty or missing headers.',
            ]);
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $rollIdx = array_search('roll_no', $header);
        if ($rollIdx === false) {
            $rollIdx = array_search('roll', $header);
        }
        if ($rollIdx === false) {
            $rollIdx = array_search('admission_no', $header);
        }

        $marksIdx = array_search('marks', $header);
        if ($marksIdx === false) {
            $marksIdx = array_search('marks_obtained', $header);
        }

        if ($rollIdx === false || $marksIdx === false) {
            throw ValidationException::withMessages([
                'csv_file' => 'CSV must contain "roll_no" and "marks" header columns.',
            ]);
        }

        $roster = $quiz->rosterQuery()->get();
        $rosterByRoll = $roster->keyBy(fn ($s) => (string) $s->roll_number);
        $rosterByAdmission = $roster->keyBy('admission_no');

        $matched = [];
        $errors = [];

        foreach ($lines as $lineNum => $line) {
            if (empty(trim($line))) continue;

            $cols = str_getcsv($line);
            $key = trim($cols[$rollIdx] ?? '');
            $val = trim($cols[$marksIdx] ?? '');

            $student = $rosterByRoll->get($key) ?? $rosterByAdmission->get($key);

            if (! $student) {
                $errors[] = "Row #" . ($lineNum + 2) . ": Student with identifier '{$key}' not found in this class section.";
                continue;
            }

            if (! is_numeric($val) || (float) $val < 0 || (float) $val > (float) $quiz->total_marks) {
                $errors[] = "Row #" . ($lineNum + 2) . ": Invalid marks '{$val}' for student '{$student->full_name}'. Must be between 0 and {$quiz->total_marks}.";
                continue;
            }

            $matched[] = [
                'student_id' => $student->id,
                'roll_number' => $student->roll_number,
                'name' => $student->full_name,
                'marks' => round((float) $val, 2),
            ];
        }

        return [
            'valid_count' => count($matched),
            'error_count' => count($errors),
            'matched' => $matched,
            'errors' => $errors,
        ];
    }
}

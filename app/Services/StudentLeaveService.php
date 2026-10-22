<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentLeaveRequest;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Notifications\StudentLeaveNotification;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentLeaveService
{
    /**
     * Submit a new leave request for the verified student.
     * Can be performed by student or authorized parent.
     */
    public function applyLeave(Student $student, array $data, ?UploadedFile $attachment = null): StudentLeaveRequest
    {
        $fromDate = CarbonImmutable::parse($data['from_date']);
        $toDate = CarbonImmutable::parse($data['to_date']);
        $today = CarbonImmutable::now()->startOfDay();

        // 1. Validation: from_date not in the past
        if ($fromDate->lt($today)) {
            throw ValidationException::withMessages([
                'from_date' => ['From date cannot be in the past.'],
            ]);
        }

        // 2. Validation: to_date >= from_date
        if ($toDate->lt($fromDate)) {
            throw ValidationException::withMessages([
                'to_date' => ['To date must be greater than or equal to from date.'],
            ]);
        }

        // 3. Validation: max 30 days
        $daysCount = $fromDate->diffInDays($toDate) + 1;
        if ($daysCount > 30) {
            throw ValidationException::withMessages([
                'to_date' => ['Leave request cannot exceed 30 consecutive days.'],
            ]);
        }

        // 4. Validation: reason 10 to 500 characters
        $reason = trim($data['reason'] ?? '');
        $len = mb_strlen($reason);
        if ($len < 10 || $len > 500) {
            throw ValidationException::withMessages([
                'reason' => ['Reason must be between 10 and 500 characters.'],
            ]);
        }

        // 5. Validation: block overlapping pending or approved requests for the same dates
        $hasOverlap = StudentLeaveRequest::where('student_id', $student->id)
            ->whereIn('status', [StudentLeaveRequest::STATUS_PENDING, StudentLeaveRequest::STATUS_APPROVED])
            ->where(function ($query) use ($fromDate, $toDate) {
                $query->where('from_date', '<=', $toDate->toDateString())
                    ->where('to_date', '>=', $fromDate->toDateString());
            })
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'from_date' => ['A pending or approved leave request already exists overlapping these dates.'],
            ]);
        }

        // 6. Handle optional attachment (PDF, JPG, PNG, max 2MB stored in private storage)
        $attachmentPath = null;
        if ($attachment) {
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
            $ext = strtolower($attachment->getClientOriginalExtension());
            if (! in_array($ext, $allowedExtensions)) {
                throw ValidationException::withMessages([
                    'attachment' => ['Attachment must be a PDF, JPG, or PNG file.'],
                ]);
            }

            if ($attachment->getSize() > 2 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'attachment' => ['Attachment size must not exceed 2 MB.'],
                ]);
            }

            // Store in private storage outside public directory
            $attachmentPath = $attachment->store('leave_attachments', 'local');
        }

        return StudentLeaveRequest::create([
            'student_id' => $student->id,
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'reason' => $reason,
            'attachment_path' => $attachmentPath,
            'status' => StudentLeaveRequest::STATUS_PENDING,
        ]);
    }

    /**
     * Cancel a pending leave request belonging to the verified student.
     */
    public function cancelLeave(Student $student, int $leaveId): StudentLeaveRequest
    {
        $leave = StudentLeaveRequest::where('student_id', $student->id)
            ->where('id', $leaveId)
            ->firstOrFail();

        if ($leave->status !== StudentLeaveRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Only pending leave requests can be cancelled.'],
            ]);
        }

        $leave->status = StudentLeaveRequest::STATUS_CANCELLED;
        $leave->save();

        return $leave;
    }

    /**
     * Admin or Teacher review (Approve / Reject) with optional review note.
     */
    public function reviewLeave(int $leaveId, string $status, ?string $note, User $reviewer): StudentLeaveRequest
    {
        $status = strtolower(trim($status));
        if (! in_array($status, [StudentLeaveRequest::STATUS_APPROVED, StudentLeaveRequest::STATUS_REJECTED])) {
            throw ValidationException::withMessages([
                'status' => ['Invalid review decision.'],
            ]);
        }

        $leave = StudentLeaveRequest::with('student')->findOrFail($leaveId);
        $student = $leave->student;

        // Check teacher permissions: Teachers can only review students of their own assigned classes
        $isAdmin = $reviewer->hasAnyRole(['Admin', 'Principal']);
        if (! $isAdmin) {
            if ($reviewer->hasRole('Teacher') && $reviewer->teacher && $reviewer->teacher->status === 'Active') {
                $isAssigned = TeacherAssignment::where('teacher_id', $reviewer->teacher->id)
                    ->where('class_id', $student->class_id)
                    ->exists();

                if (! $isAssigned) {
                    abort(403, 'You can only review leave requests for your assigned classes.');
                }
            } else {
                abort(403, 'Unauthorized to review student leave requests.');
            }
        }

        DB::transaction(function () use ($leave, $student, $status, $note, $reviewer) {
            $leave->status = $status;
            $leave->review_note = $note;
            $leave->reviewed_by = $reviewer->id;
            $leave->reviewed_at = now();
            $leave->save();

            // When approved, automatically mark attendance as "leave" for working days
            // Do not overwrite days where attendance is already marked present (or late)
            if ($status === StudentLeaveRequest::STATUS_APPROVED) {
                $fromDate = CarbonImmutable::parse($leave->from_date);
                $toDate = CarbonImmutable::parse($leave->to_date);

                $holidays = Holiday::whereDate('start_date', '<=', $toDate->toDateString())
                    ->whereDate('end_date', '>=', $fromDate->toDateString())
                    ->get();

                for ($cursor = $fromDate; $cursor->lte($toDate); $cursor = $cursor->addDay()) {
                    $dateStr = $cursor->toDateString();

                    // Only for working days (exclude Sundays and holidays)
                    $isSunday = $cursor->isSunday();
                    $isHoliday = $holidays->contains(fn ($h) => $cursor->betweenIncluded($h->start_date, $h->end_date));

                    if ($isSunday || $isHoliday) {
                        continue;
                    }

                    // Check existing attendance on this date
                    $existing = StudentAttendance::where('student_id', $student->id)
                        ->where('date', $dateStr)
                        ->first();

                    if ($existing) {
                        // Do not overwrite days already marked present or late
                        if (in_array(strtolower($existing->status), ['present', 'half_day', 'late'])) {
                            continue;
                        }

                        $existing->status = 'leave';
                        $existing->remarks = "Approved leave request #{$leave->id}".($note ? " ({$note})" : '');
                        $existing->marked_by = $reviewer->id;
                        $existing->save();
                    } else {
                        StudentAttendance::create([
                            'student_id' => $student->id,
                            'date' => $dateStr,
                            'attendance_date' => $dateStr,
                            'school_class_id' => $student->class_id,
                            'section_id' => $student->section_id,
                            'academic_session_id' => $student->academic_session_id,
                            'status' => 'leave',
                            'remarks' => "Approved leave request #{$leave->id}".($note ? " ({$note})" : ''),
                            'marked_by' => $reviewer->id,
                        ]);
                    }
                }
            }
        });

        // Send notifications to student user and parent users
        try {
            if ($student->user) {
                $student->user->notify(new StudentLeaveNotification($leave, $status, $note));
            }

            foreach ($student->parents as $parentUser) {
                $parentUser->notify(new StudentLeaveNotification($leave, $status, $note));
            }
        } catch (\Throwable $e) {
            // Log but don't fail transaction if notification dispatch encounters an issue
            report($e);
        }

        return $leave;
    }
}

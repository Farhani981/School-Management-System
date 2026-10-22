<?php

namespace App\Notifications;

use App\Models\StudentLeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentLeaveNotification extends Notification
{
    use Queueable;

    public function __construct(
        public StudentLeaveRequest $leaveRequest,
        public string $status,
        public ?string $note = null
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $studentName = $this->leaveRequest->student?->full_name ?? 'Student';
        $from = $this->leaveRequest->from_date?->format('M d, Y');
        $to = $this->leaveRequest->to_date?->format('M d, Y');

        return [
            'leave_request_id' => $this->leaveRequest->id,
            'student_id' => $this->leaveRequest->student_id,
            'student_name' => $studentName,
            'status' => $this->status,
            'review_note' => $this->note,
            'title' => 'Leave Request '.ucfirst($this->status),
            'message' => "Leave request for {$studentName} ({$from} to {$to}) has been {$this->status}.".($this->note ? " Note: {$this->note}" : ''),
        ];
    }
}

<?php

namespace App\Jobs;

use App\Models\StudentAttendance;
use App\Services\AttendanceNotificationGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAbsenceNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $attendanceId) {}

    public function handle(AttendanceNotificationGateway $gateway): void
    {
        $record = StudentAttendance::with('student')->find($this->attendanceId);
        if ($record?->status !== 'absent' || ! $record->student) {
            return;
        }
        $phone = $record->student->guardian_phone ?: $record->student->phone;
        if ($phone) {
            $gateway->send($phone, 'Your child was marked absent on '.$record->date->format('d M Y').'.', $record->id);
        }
    }
}

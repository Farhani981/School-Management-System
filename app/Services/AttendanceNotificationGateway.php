<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AttendanceNotificationGateway
{
    public function send(string $phone, string $message, int $attendanceId): void
    {
        // Replace with your SMS/WhatsApp provider. No external message is sent yet.
        Log::info('Absence notification placeholder', ['attendance_id' => $attendanceId, 'channel' => config('attendance.notification_channel')]);
    }
}

<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
class ExamWorkflowUpdated extends Notification
{
    public function __construct(public array $data) {}
    public function via($notifiable): array { return ['database']; }
    public function toArray($notifiable): array { return $this->data; }
}

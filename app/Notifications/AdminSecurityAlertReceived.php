<?php

namespace App\Notifications;

use App\Models\AdminLoginEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminSecurityAlertReceived extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AdminLoginEvent $event,
        public readonly string $kind,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'event_id' => $this->event->id,
            'source_type' => 'login_burst',
        ];
    }
}

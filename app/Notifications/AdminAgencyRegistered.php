<?php

namespace App\Notifications;

use App\Models\Agency;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminAgencyRegistered extends Notification
{
    use Queueable;

    public function __construct(public readonly Agency $agency) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'agency_id' => $this->agency->id,
            'agency_name' => $this->agency->company_name,
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\TravelRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminCustomRequestReceived extends Notification
{
    use Queueable;

    public function __construct(public readonly TravelRequest $travelRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'travel_request_id' => $this->travelRequest->id,
            'reference' => $this->travelRequest->reference,
        ];
    }
}

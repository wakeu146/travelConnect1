<?php

namespace App\Notifications;

use App\Models\TravelerIssue;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminTravelerIssueReceived extends Notification
{
    use Queueable;

    public function __construct(public readonly TravelerIssue $issue) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'issue_id' => $this->issue->id,
            'issue_type' => $this->issue->type,
            'subject' => $this->issue->subject,
        ];
    }
}

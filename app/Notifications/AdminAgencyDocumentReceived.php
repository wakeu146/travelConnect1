<?php

namespace App\Notifications;

use App\Models\AgencyDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminAgencyDocumentReceived extends Notification
{
    use Queueable;

    public function __construct(public readonly AgencyDocument $document) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'agency_id' => $this->document->agency->id,
            'agency_name' => $this->document->agency->company_name,
            'document_id' => $this->document->id,
            'document_type' => $this->document->type->value,
        ];
    }
}

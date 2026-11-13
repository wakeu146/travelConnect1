<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AgencyModerationMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $heading;

    public function __construct(
        public readonly string $agencyName,
        public readonly string $body,
        ?string $heading = null,
        private readonly ?string $subjectLine = null,
    ) {
        $this->heading = $heading ?? __('messages.agency_moderation_email_heading');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine ?? __('messages.agency_moderation_email_subject', [
                'agency' => $this->agencyName,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.agency-moderation',
            text: 'emails.agency-moderation-text',
            with: [
                'heading' => $this->heading,
                'agencyName' => $this->agencyName,
                'body' => $this->body,
            ],
        );
    }
}

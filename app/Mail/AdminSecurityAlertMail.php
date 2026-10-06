<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminSecurityAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $alertMessage,
        public readonly string $time,
        public readonly ?string $ipAddress,
        public readonly ?string $device,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.admin_security_alert_subject'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-security-alert',
            text: 'emails.admin-security-alert-text',
            with: [
                'name' => $this->name,
                'alertMessage' => $this->alertMessage,
                'time' => $this->time,
                'ipAddress' => $this->ipAddress,
                'device' => $this->device,
            ],
        );
    }
}

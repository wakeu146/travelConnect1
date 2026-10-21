<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminAccountSecurityMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $action,
        public readonly string $time,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.admin_users.security_email_subjects.'.$this->action),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-account-security',
            text: 'emails.admin-account-security-text',
            with: [
                'name' => $this->name,
                'action' => $this->action,
                'time' => $this->time,
            ],
        );
    }
}

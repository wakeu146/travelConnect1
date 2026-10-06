<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminPasswordResetNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $name) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.admin_password_reset_notice_subject'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-password-reset-notice',
            text: 'emails.admin-password-reset-notice-text',
            with: ['name' => $this->name],
        );
    }
}

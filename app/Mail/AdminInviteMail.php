<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AdminInviteMail extends Mailable
{
    public function __construct(
        public readonly string $name,
        public readonly string $link,
        public readonly int $ttlHours,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You have been invited to the APFSPN admin dashboard');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.admin-invite');
    }
}

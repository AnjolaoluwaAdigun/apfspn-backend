<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AdminDisabledMail extends Mailable
{
    public function __construct(public readonly string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your APFSPN admin account has been disabled');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.admin-disabled');
    }
}

<?php

namespace App\Mail;

use App\Enums\MemberType;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ApplicationReceivedMail extends Mailable
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $organisationName,
        public readonly MemberType $memberType,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'We have received your APFSPN membership application');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.application-received');
    }
}

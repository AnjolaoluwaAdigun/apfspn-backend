<?php

namespace App\Mail;

use App\Enums\EventNotice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EventRegistrationMail extends Mailable
{
    public function __construct(
        public readonly EventNotice $notice,
        public readonly string $name,
        public readonly string $eventTitle,
        public readonly string $startsAt,
        public readonly string $location,
        public readonly ?string $virtualLink = null,
        public readonly ?int $waitlistPosition = null,
        public readonly ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notice->subject($this->eventTitle));
    }

    public function content(): Content
    {
        return new Content(view: $this->notice->view(), with: ['portalUrl' => config('apfspn.frontend.member_url')]);
    }
}

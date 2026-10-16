<?php

namespace App\Mail;

use App\Enums\MembershipNotice;
use App\Enums\MemberType;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MembershipStatusMail extends Mailable
{
    public function __construct(
        public readonly MembershipNotice $notice,
        public readonly string $name,
        public readonly MemberType $memberType,
        public readonly ?string $memberId = null,
        public readonly ?string $reason = null,
        public readonly ?string $expiresOn = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notice->subject());
    }

    public function content(): Content
    {
        return new Content(view: $this->notice->view(), with: ['portalUrl' => config('apfspn.frontend.member_url')]);
    }
}

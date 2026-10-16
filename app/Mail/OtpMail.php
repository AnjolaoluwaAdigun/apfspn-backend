<?php

namespace App\Mail;

use App\Enums\ChallengePurpose;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OtpMail extends Mailable
{
    public function __construct(
        public readonly string $code,
        public readonly ChallengePurpose $purpose,
        public readonly int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->purpose) {
            ChallengePurpose::PasswordReset => 'Your APFSPN password reset code',
            ChallengePurpose::EmailVerification => 'Verify your APFSPN email address',
            default => 'Your APFSPN sign-in code',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'mail.otp');
    }
}

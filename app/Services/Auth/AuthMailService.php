<?php

namespace App\Services\Auth;

use App\Enums\ChallengePurpose;
use App\Mail\AdminInviteMail;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Mail;

class AuthMailService
{
    public function sendOtp(string $email, string $code, ChallengePurpose $purpose): void
    {
        Mail::to($email)->send(new OtpMail($code, $purpose, config('apfspn.auth.otp_ttl_minutes')));
    }

    public function sendAdminInvite(string $name, string $email, string $link): void
    {
        Mail::to($email)->send(new AdminInviteMail($name, $link, config('apfspn.auth.invite_token_ttl_hours')));
    }
}

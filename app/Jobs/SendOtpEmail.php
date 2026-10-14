<?php

namespace App\Jobs;

use App\Enums\ChallengePurpose;
use App\Services\Auth\AuthMailService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOtpEmail implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly string $email,
        public readonly string $code,
        public readonly ChallengePurpose $purpose,
        public readonly string $challengeUuid,
        public readonly int $version,
    ) {}

    public function uniqueId(): string
    {
        return $this->challengeUuid.':'.$this->version;
    }

    public function handle(AuthMailService $mail): void
    {
        $mail->sendOtp($this->email, $this->code, $this->purpose);
    }
}

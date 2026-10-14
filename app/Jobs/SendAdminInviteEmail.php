<?php

namespace App\Jobs;

use App\Services\Auth\AuthMailService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAdminInviteEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $link,
    ) {}

    public function handle(AuthMailService $mail): void
    {
        $mail->sendAdminInvite($this->name, $this->email, $this->link);
    }
}

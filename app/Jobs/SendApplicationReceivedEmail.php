<?php

namespace App\Jobs;

use App\Services\Membership\MembershipMailService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendApplicationReceivedEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public readonly string $membershipUuid) {}

    public function uniqueId(): string
    {
        return $this->membershipUuid;
    }

    public function handle(MembershipMailService $mail): void
    {
        $mail->sendApplicationReceived($this->membershipUuid);
    }
}

<?php

namespace App\Jobs;

use App\Enums\MembershipNotice;
use App\Services\Membership\MembershipMailService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMembershipStatusEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $membershipUuid,
        public readonly MembershipNotice $notice,
        public readonly string $occurredAt,
    ) {}

    public function uniqueId(): string
    {
        return implode(':', [$this->membershipUuid, $this->notice->value, $this->occurredAt]);
    }

    public function handle(MembershipMailService $mail): void
    {
        $mail->sendStatusNotice($this->membershipUuid, $this->notice);
    }
}

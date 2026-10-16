<?php

namespace App\Jobs;

use App\Enums\EventNotice;
use App\Services\Events\EventMailService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendEventRegistrationEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $registrationUuid,
        public readonly EventNotice $notice,
        public readonly string $occurredAt,
    ) {}

    public function uniqueId(): string
    {
        return implode(':', [$this->registrationUuid, $this->notice->value, $this->occurredAt]);
    }

    public function handle(EventMailService $mail): void
    {
        $mail->send($this->registrationUuid, $this->notice);
    }
}

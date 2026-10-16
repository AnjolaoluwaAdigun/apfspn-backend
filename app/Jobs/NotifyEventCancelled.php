<?php

namespace App\Jobs;

use App\Services\Events\EventMailService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyEventCancelled implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public readonly string $eventUuid) {}

    public function uniqueId(): string
    {
        return $this->eventUuid;
    }

    public function handle(EventMailService $mail): void
    {
        $mail->notifyCancelled($this->eventUuid);
    }
}

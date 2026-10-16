<?php

namespace App\Jobs;

use App\Services\AdminUsers\AdminUserMailService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAdminDisabledEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $adminUuid,
        public readonly string $occurredAt,
    ) {}

    public function uniqueId(): string
    {
        return implode(':', [$this->adminUuid, 'disabled', $this->occurredAt]);
    }

    public function handle(AdminUserMailService $mail): void
    {
        $mail->sendDisabled($this->adminUuid);
    }
}

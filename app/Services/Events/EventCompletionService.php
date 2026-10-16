<?php

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Repositories\Contracts\EventRepositoryInterface;
use Illuminate\Support\Facades\DB;

class EventCompletionService
{
    public function __construct(private readonly EventRepositoryInterface $events) {}

    public function completePast(bool $dryRun = false): int
    {
        $uuids = $this->events->uuidsEndedBefore(now());

        if ($dryRun) {
            return $uuids->count();
        }

        return $uuids->sum(fn (string $uuid) => DB::transaction(function () use ($uuid) {
            $event = $this->events->findForUpdate($uuid);

            if ($event?->status !== EventStatus::Published || ! $event->ends_at->isPast()) {
                return 0;
            }

            $event->status = EventStatus::Completed;
            $this->events->save($event);

            return 1;
        }, 3));
    }
}

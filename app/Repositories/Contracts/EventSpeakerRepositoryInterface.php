<?php

namespace App\Repositories\Contracts;

use App\Models\EventSpeaker;
use Illuminate\Support\Collection;

interface EventSpeakerRepositoryInterface
{
    public function forEvent(string $eventUuid): Collection;

    public function create(array $attributes): EventSpeaker;

    public function save(EventSpeaker $speaker): EventSpeaker;

    public function deleteForEventExcept(string $eventUuid, array $keepUuids): int;
}

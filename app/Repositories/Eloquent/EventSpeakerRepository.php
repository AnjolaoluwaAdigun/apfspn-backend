<?php

namespace App\Repositories\Eloquent;

use App\Models\EventSpeaker;
use App\Repositories\Contracts\EventSpeakerRepositoryInterface;
use Illuminate\Support\Collection;

class EventSpeakerRepository implements EventSpeakerRepositoryInterface
{
    public function forEvent(string $eventUuid): Collection
    {
        return EventSpeaker::where('event_uuid', $eventUuid)->orderBy('sort_order')->get()->keyBy('uuid');
    }

    public function create(array $attributes): EventSpeaker
    {
        return EventSpeaker::create($attributes);
    }

    public function save(EventSpeaker $speaker): EventSpeaker
    {
        $speaker->save();

        return $speaker;
    }

    public function deleteForEventExcept(string $eventUuid, array $keepUuids): int
    {
        return EventSpeaker::where('event_uuid', $eventUuid)->whereNotIn('uuid', $keepUuids)->delete();
    }
}

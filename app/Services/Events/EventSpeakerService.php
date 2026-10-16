<?php

namespace App\Services\Events;

use App\Helpers\FileUploadHelper;
use App\Models\Event;
use App\Repositories\Contracts\EventSpeakerRepositoryInterface;
use Illuminate\Support\Arr;

class EventSpeakerService
{
    private const FIELDS = ['name', 'title', 'bio'];

    public function __construct(private readonly EventSpeakerRepositoryInterface $speakers) {}

    public function prepare(?array $speakers): ?array
    {
        if ($speakers === null) {
            return null;
        }

        return array_map(function (array $speaker) {
            $photoUrl = FileUploadHelper::upload($speaker['photo'] ?? null, config('apfspn.uploads.folders.event_speakers'));

            return Arr::except($speaker, 'photo') + ($photoUrl !== null ? ['photo_url' => $photoUrl] : []);
        }, array_values($speakers));
    }

    public function sync(Event $event, ?array $prepared): bool
    {
        if ($prepared === null) {
            return false;
        }

        $existing = $this->speakers->forEvent($event->getKey());
        $keep = array_values(array_filter(array_column($prepared, 'uuid')));
        $changed = $this->speakers->deleteForEventExcept($event->getKey(), $keep) > 0;

        foreach ($prepared as $index => $data) {
            $attributes = Arr::only($data, [...self::FIELDS, 'photo_url']) + ['sort_order' => $index];
            $speaker = isset($data['uuid']) ? $existing->get($data['uuid']) : null;

            if ($speaker === null) {
                $this->speakers->create($attributes + ['event_uuid' => $event->getKey()]);
                $changed = true;

                continue;
            }

            $speaker->fill($attributes);

            if ($speaker->isDirty()) {
                $this->speakers->save($speaker);
                $changed = true;
            }
        }

        $event->unsetRelation('speakers');

        return $changed;
    }
}

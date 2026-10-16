<?php

namespace App\Http\Requests\Concerns;

use App\Enums\EventAudience;
use App\Enums\EventStatus;
use App\Rules\UploadableFile;
use Illuminate\Validation\Rule;

trait EventRules
{
    private const PROHIBITED_EVENT_FIELDS = [
        'uuid', 'status', 'image_url', 'published_at', 'cancelled_at', 'cancellation_reason',
        'created_by_admin_uuid', 'updated_by_admin_uuid', 'deleted_at',
        'price', 'member_price', 'non_member_price',
    ];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    protected function eventRules(?string $eventUuid = null): array
    {
        $updating = $eventUuid !== null;
        $required = $updating ? ['sometimes', 'required'] : ['required'];
        $optional = $updating ? ['sometimes', 'nullable'] : ['nullable'];

        return array_merge(array_fill_keys(self::PROHIBITED_EVENT_FIELDS, ['prohibited']), [
            'title' => [...$required, 'string', 'max:200'],
            'slug' => [...$optional, 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('events', 'slug')->ignore($eventUuid, 'uuid')],
            'description' => [...$required, 'string', 'max:20000'],
            'image' => [...$optional, new UploadableFile(self::IMAGE_EXTENSIONS, allowOwnUrl: true)],
            'starts_at' => [...$required, 'date'],
            'ends_at' => [...$required, 'date', ...($this->filled('starts_at') ? ['after:starts_at'] : [])],
            'venue' => [...$optional, 'string', 'max:255'],
            'address' => [...$optional, 'string', 'max:500'],
            'city' => [...$optional, 'string', 'max:100'],
            'state_code' => [...$optional, 'string', Rule::in(array_keys(config('apfspn.states')))],
            'is_virtual' => [$updating ? 'sometimes' : 'nullable', 'boolean'],
            'virtual_link' => [...$optional, 'url:https,http', 'max:500'],
            'audience' => [$updating ? 'sometimes' : 'nullable', Rule::enum(EventAudience::class)],
            'capacity' => [...$optional, 'integer', 'min:1', 'max:1000000'],
            'registration_closes_at' => [...$optional, 'date'],
            'schedule' => [...$optional, 'array', 'max:50'],
            'schedule.*' => ['array:time,title,description'],
            'schedule.*.time' => ['required', 'string', 'max:50'],
            'schedule.*.title' => ['required', 'string', 'max:200'],
            'schedule.*.description' => ['nullable', 'string', 'max:1000'],
            'speakers' => [$updating ? 'sometimes' : 'nullable', 'array', 'max:30'],
            'speakers.*' => ['array:uuid,name,title,bio,photo'],
            'speakers.*.uuid' => $updating
                ? ['nullable', 'uuid', 'distinct', Rule::exists('event_speakers', 'uuid')->where('event_uuid', $eventUuid)]
                : ['prohibited'],
            'speakers.*.name' => ['required', 'string', 'max:200'],
            'speakers.*.title' => ['nullable', 'string', 'max:200'],
            'speakers.*.bio' => ['nullable', 'string', 'max:5000'],
            'speakers.*.photo' => ['nullable', new UploadableFile(self::IMAGE_EXTENSIONS, allowOwnUrl: true)],
        ]);
    }

    protected function eventFilterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(EventStatus::class)],
            'audience' => ['nullable', Rule::enum(EventAudience::class)],
            'starts_from' => ['nullable', 'date_format:Y-m-d'],
            'starts_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_from'],
        ];
    }
}

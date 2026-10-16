<?php

namespace App\Http\Resources;

use App\Enums\EventAudience;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = $request->routeIs('v1.admin.*');
        $counts = $this->registrationCounts;
        $seatsTaken = $counts === null ? null : $counts['registered'] + $counts['attended'];

        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'venue' => $this->venue,
            'address' => $this->address,
            'city' => $this->city,
            'state_code' => $this->state_code,
            'state' => $this->state_code ? config('apfspn.states.'.$this->state_code) : null,
            'is_virtual' => $this->is_virtual,
            'virtual_link' => $this->when($isAdmin, $this->virtual_link),
            'audience' => $this->audience,
            'members_only' => $this->audience === EventAudience::MembersOnly,
            'capacity' => $this->capacity,
            'seats_left' => $this->when($seatsTaken !== null, fn () => $this->capacity === null ? null : max(0, $this->capacity - $seatsTaken)),
            'registration_closes_at' => $this->registrationClosesAt()?->toIso8601String(),
            'registration_open' => $this->registrationOpen(),
            'schedule' => $this->schedule ?? [],
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'cancellation_reason' => $this->when($isAdmin || $this->cancellation_reason !== null, $this->cancellation_reason),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'registered_count' => $this->when($isAdmin && $counts !== null, fn () => $counts['registered']),
            'waitlisted_count' => $this->when($isAdmin && $counts !== null, fn () => $counts['waitlisted']),
            'attended_count' => $this->when($isAdmin && $counts !== null, fn () => $counts['attended']),
            'published_at' => $this->when($isAdmin, fn () => $this->published_at?->toIso8601String()),
            'created_by_admin_uuid' => $this->when($isAdmin, $this->created_by_admin_uuid),
            'updated_by_admin_uuid' => $this->when($isAdmin, $this->updated_by_admin_uuid),
            'created_at' => $this->when($isAdmin, fn () => $this->created_at?->toIso8601String()),
            'updated_at' => $this->when($isAdmin, fn () => $this->updated_at?->toIso8601String()),
            'speakers' => EventSpeakerResource::collection($this->whenLoaded('speakers')),
        ];
    }
}

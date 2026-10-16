<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = $request->routeIs('v1.admin.*');

        return [
            'uuid' => $this->uuid,
            'event_uuid' => $this->event_uuid,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'waitlist_position' => $this->waitlist_position,
            'registered_at' => $this->registered_at?->toIso8601String(),
            'promoted_at' => $this->promoted_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'attended_at' => $this->attended_at?->toIso8601String(),
            'member' => $this->when($isAdmin && $this->relationLoaded('user'), fn () => $this->user ? [
                'uuid' => $this->user->uuid,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'member_id' => $this->user->membership?->member_id,
                'member_type' => $this->user->membership?->member_type,
                'organisation' => $this->user->organisationProfile?->name,
            ] : null),
            'virtual_link' => $this->when(! $isAdmin && $this->relationLoaded('event'), fn () => $this->status->holdsSeat() && $this->event?->is_virtual
                ? $this->event->virtual_link
                : null),
            'event' => $this->when(! $isAdmin && $this->relationLoaded('event'), fn () => new EventResource($this->event)),
        ];
    }
}

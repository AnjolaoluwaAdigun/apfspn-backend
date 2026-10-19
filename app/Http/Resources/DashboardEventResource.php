<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $counts = $this->registrationCounts ?? [];
        $seatsTaken = ($counts['registered'] ?? 0) + ($counts['attended'] ?? 0);

        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'title' => $this->title,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'venue' => $this->venue,
            'is_virtual' => $this->is_virtual,
            'audience' => $this->audience,
            'capacity' => $this->capacity,
            'registered_count' => $counts['registered'] ?? 0,
            'waitlisted_count' => $counts['waitlisted'] ?? 0,
            'seats_left' => $this->capacity === null ? null : max(0, $this->capacity - $seatsTaken),
        ];
    }
}

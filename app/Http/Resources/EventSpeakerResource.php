<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventSpeakerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'title' => $this->title,
            'bio' => $this->bio,
            'photo_url' => $this->photo_url,
            'sort_order' => $this->sort_order,
        ];
    }
}

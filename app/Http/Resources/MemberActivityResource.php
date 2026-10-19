<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class MemberActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'label' => $this->type->label(),
            'subject' => $this->subject_type === null ? null : [
                'type' => Str::snake(class_basename($this->subject_type)),
                'uuid' => $this->subject_uuid,
                'title' => $this->subject instanceof Event ? $this->subject->title : null,
                'slug' => $this->subject instanceof Event ? $this->subject->slug : null,
            ],
            'changed_fields' => $this->properties['changed_fields'] ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

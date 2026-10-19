<?php

namespace App\Http\Resources;

use App\Models\Admin;
use App\Models\Article;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ActivityFeedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'action' => $this->action,
            'description' => Str::ucfirst(str_replace(['.', '_'], ' ', $this->action)),
            'admin' => $this->admin ? ['uuid' => $this->admin->uuid, 'name' => $this->admin->name] : null,
            'subject' => $this->auditable_type === null ? null : [
                'type' => Str::snake(class_basename($this->auditable_type)),
                'uuid' => $this->auditable_uuid,
                'label' => $this->label($this->auditable),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function label(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof User, $subject instanceof Admin, $subject instanceof Role => $subject->name,
            $subject instanceof Membership => $subject->user?->name,
            $subject instanceof Event, $subject instanceof Article => $subject->title,
            $subject instanceof EventRegistration => trim($subject->user?->name.' / '.$subject->event?->title, ' /'),
            default => null,
        };
    }
}

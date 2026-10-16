<?php

namespace App\Http\Resources;

use App\Services\AdminUsers\RoleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'label' => str($this->name)->headline()->value(),
            'built_in' => RoleService::isBuiltIn($this->name),
            'locked' => RoleService::isLocked($this->name),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')->sort()->values()),
            'admins_count' => $this->when($this->admins_count !== null, fn () => $this->admins_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

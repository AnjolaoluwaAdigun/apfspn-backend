<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'must_reset_password' => $this->must_reset_password,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'roles' => $this->roles->pluck('name')->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values(),
        ];
    }
}

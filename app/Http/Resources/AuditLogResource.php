<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'admin_uuid' => $this->admin_uuid,
            'action' => $this->action,
            'auditable_type' => $this->auditable_type,
            'auditable_uuid' => $this->auditable_uuid,
            'method' => $this->method,
            'path' => $this->path,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'request_body' => $this->request_body,
            'changes' => $this->changes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

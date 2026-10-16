<?php

namespace App\Http\Resources;

use App\Enums\MembershipStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'member_type' => $this->member_type,
            'member_id' => $this->member_id,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'state_code' => $this->state_code,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'rejection_reason' => $this->when($this->status === MembershipStatus::Rejected, $this->rejection_reason),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

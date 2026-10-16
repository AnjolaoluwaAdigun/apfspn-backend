<?php

namespace App\Http\Resources;

use App\Enums\MembershipStatus;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user() instanceof Admin;

        return [
            'uuid' => $this->uuid,
            'member_type' => $this->member_type,
            'member_id' => $this->member_id,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'state_code' => $this->state_code,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'rejection_reason' => $this->when($isAdmin || $this->status === MembershipStatus::Rejected, $this->rejection_reason),
            'suspension_reason' => $this->when($isAdmin || $this->status === MembershipStatus::Suspended, $this->suspension_reason),
            'approved_by_admin_uuid' => $this->when($isAdmin, $this->approved_by_admin_uuid),
            'rejected_at' => $this->when($isAdmin, fn () => $this->rejected_at?->toIso8601String()),
            'rejected_by_admin_uuid' => $this->when($isAdmin, $this->rejected_by_admin_uuid),
            'suspended_at' => $this->when($isAdmin, fn () => $this->suspended_at?->toIso8601String()),
            'suspended_by_admin_uuid' => $this->when($isAdmin, $this->suspended_by_admin_uuid),
            'reactivated_at' => $this->when($isAdmin, fn () => $this->reactivated_at?->toIso8601String()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

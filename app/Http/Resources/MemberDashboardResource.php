<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $member = $this->resource['member'];
        $membership = $member->membership;

        return [
            'welcome_name' => $member->name,
            'organisation_name' => $member->organisationProfile?->name,
            'membership_card' => $membership ? [
                'member_type' => $membership->member_type,
                'member_type_label' => $membership->member_type->label(),
                'member_id' => $membership->member_id,
                'status' => $membership->status,
                'status_label' => $membership->status->label(),
                'approved_at' => $membership->approved_at?->toIso8601String(),
                'expires_at' => $membership->expires_at?->toIso8601String(),
                'days_until_expiry' => $membership->expires_at
                    ? (int) now()->startOfDay()->diffInDays($membership->expires_at->copy()->startOfDay(), false)
                    : null,
            ] : null,
            'upcoming_events' => EventRegistrationResource::collection($this->resource['upcoming_events']),
            'waitlisted_events' => EventRegistrationResource::collection($this->resource['waitlisted_events']),
            'recent_activity' => MemberActivityResource::collection($this->resource['recent_activity']),
        ];
    }
}

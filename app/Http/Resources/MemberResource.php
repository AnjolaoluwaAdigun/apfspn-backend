<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'account_status' => $this->status,
            'member_type' => $this->whenLoaded('membership', fn () => $this->membership?->member_type),
            'bio' => $this->bio,
            'photo_url' => $this->photo_url,
            'state_code' => $this->state_code,
            'state' => $this->state_code ? config('apfspn.states.'.$this->state_code) : null,
            'city' => $this->city,
            'directory_visible' => $this->directory_visible,
            'two_factor_enabled' => $this->two_factor_enabled,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'membership' => $this->whenLoaded('membership', fn () => $this->membership ? new MembershipResource($this->membership) : null),
            'organisation' => $this->whenLoaded('organisationProfile', fn () => $this->organisationProfile ? new OrganisationProfileResource($this->organisationProfile) : null),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Helpers\FileUploadHelper;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganisationProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ttl = config('apfspn.uploads.private_url_ttl_minutes');
        $documentUrl = $this->canSeeDocument($request) ? FileUploadHelper::temporaryUrl($this->document_public_id, $ttl) : null;

        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'name' => $this->name,
            'registration_number' => $this->registration_number,
            'year_established' => $this->year_established,
            'business_type' => $this->business_type,
            'institution_type' => $this->institution_type,
            'department' => $this->department,
            'address' => $this->address,
            'state_code' => $this->state_code,
            'state' => config('apfspn.states.'.$this->state_code),
            'city' => $this->city,
            'website' => $this->website,
            'document_url' => $documentUrl,
            'document_url_expires_at' => $documentUrl !== null ? now()->addMinutes($ttl)->toIso8601String() : null,
        ];
    }

    private function canSeeDocument(Request $request): bool
    {
        $viewer = $request->user();

        return $viewer instanceof Admin
            || ($viewer instanceof User && $viewer->getKey() === $this->user_uuid);
    }
}

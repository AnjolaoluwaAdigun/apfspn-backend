<?php

namespace App\Http\Requests\Member\Profile;

use App\Enums\MemberType;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\UploadRules;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends ApiFormRequest
{
    use UploadRules;

    public function rules(): array
    {
        $type = $this->user()->organisationProfile?->type;

        return array_merge($this->photoRules(), array_fill_keys([
            'uuid', 'user_uuid', 'name', 'email', 'status', 'membership_status', 'member_id', 'member_type', 'state_code',
            'company_name', 'institution_name', 'registration_number', 'institution_type', 'year_established',
            'email_verified_at', 'approved_at', 'two_factor_enabled', 'password', 'document_public_id',
            'registration_document', 'letter_of_authorisation',
        ], ['prohibited']), [
            'phone' => ['sometimes', 'required', 'string', 'regex:/^\+?[0-9]{7,15}$/', Rule::unique('users', 'phone')->ignore($this->user()->getKey(), 'uuid')],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'directory_visible' => ['sometimes', 'boolean'],
            'address' => [Rule::prohibitedIf($type === null), 'sometimes', 'required', 'string', 'max:500'],
            'website' => [Rule::prohibitedIf($type !== MemberType::Corporate), 'sometimes', 'nullable', 'url:https,http', 'max:255'],
            'business_type' => [Rule::prohibitedIf($type !== MemberType::Corporate), 'sometimes', 'required', 'string', 'max:100'],
            'department' => [Rule::prohibitedIf($type !== MemberType::Institutional), 'sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }
}

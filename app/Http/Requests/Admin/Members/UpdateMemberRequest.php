<?php

namespace App\Http\Requests\Admin\Members;

use App\Enums\MemberType;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\MembershipActionRules;
use App\Models\User;
use App\Repositories\Contracts\MemberRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends ApiFormRequest
{
    use MembershipActionRules;

    private const ORGANISATION_KEYS = 'name,registration_number,year_established,business_type,institution_type,department,address,state_code,city,website';

    private ?User $member = null;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('state_code'))) {
            $this->merge(['state_code' => Str::upper(trim($this->input('state_code')))]);
        }

        if (is_array($this->input('organisation')) && is_string($this->input('organisation.state_code'))) {
            $this->merge(['organisation' => ['state_code' => Str::upper(trim($this->input('organisation.state_code')))] + $this->input('organisation')]);
        }
    }

    public function rules(): array
    {
        $member = $this->member();
        $type = $member->organisationProfile?->type;
        $states = array_keys(config('apfspn.states'));
        $corporateOnly = Rule::prohibitedIf($type !== MemberType::Corporate);
        $institutionalOnly = Rule::prohibitedIf($type !== MemberType::Institutional);

        return array_merge($this->prohibitedMembershipRules(), array_fill_keys([
            'email', 'email_verified_at', 'password', 'two_factor_enabled', 'photo', 'photo_url', 'document_public_id',
            'registration_document', 'letter_of_authorisation', 'rejection_reason', 'suspension_reason', 'deleted_at',
        ], ['prohibited']), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^\+?[0-9]{7,15}$/', Rule::unique('users', 'phone')->ignore($member->getKey(), 'uuid')],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'state_code' => [Rule::prohibitedIf($type !== null), 'sometimes', 'required', 'string', Rule::in($states)],
            'city' => [Rule::prohibitedIf($type !== null), 'sometimes', 'nullable', 'string', 'max:100'],
            'directory_visible' => ['sometimes', 'boolean'],
            'organisation' => [Rule::prohibitedIf($type === null), 'sometimes', 'array:'.self::ORGANISATION_KEYS],
            'organisation.name' => ['sometimes', 'required', 'string', 'max:255'],
            'organisation.registration_number' => ['sometimes', 'required', 'string', 'max:100'],
            'organisation.year_established' => [$corporateOnly, 'sometimes', 'nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'organisation.business_type' => [$corporateOnly, 'sometimes', 'required', 'string', 'max:100'],
            'organisation.website' => [$corporateOnly, 'sometimes', 'nullable', 'url:https,http', 'max:255'],
            'organisation.institution_type' => [$institutionalOnly, 'sometimes', 'required', 'string', 'max:100'],
            'organisation.department' => [$institutionalOnly, 'sometimes', 'nullable', 'string', 'max:255'],
            'organisation.address' => ['sometimes', 'required', 'string', 'max:500'],
            'organisation.state_code' => ['sometimes', 'required', 'string', Rule::in($states)],
            'organisation.city' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
    }

    private function member(): User
    {
        return $this->member ??= app(MemberRepositoryInterface::class)->findWithProfile((string) $this->route('member'));
    }
}

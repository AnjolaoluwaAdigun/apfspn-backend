<?php

namespace App\Services\Membership;

use App\Enums\MemberActivityType;
use App\Helpers\FileUploadHelper;
use App\Models\User;
use App\Repositories\Contracts\OrganisationProfileRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MemberProfileService
{
    private const USER_FIELDS = ['phone', 'bio', 'directory_visible'];

    private const ORGANISATION_FIELDS = ['address', 'website', 'business_type', 'department'];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly OrganisationProfileRepositoryInterface $organisations,
        private readonly MemberActivityService $activities,
    ) {}

    public function show(User $user): User
    {
        return $this->users->withProfile($user);
    }

    public function update(User $user, array $data): User
    {
        $photo = array_key_exists('photo', $data)
            ? FileUploadHelper::upload($data['photo'], config('apfspn.uploads.folders.member_photos'), $user->photo_url)
            : $user->photo_url;

        DB::transaction(function () use ($user, $data, $photo) {
            $locked = $this->users->findForUpdate($user->getKey());
            $organisation = $this->organisations->findForUserForUpdate($locked->getKey());

            $this->users->update($locked, Arr::only($data, $organisation ? self::USER_FIELDS : [...self::USER_FIELDS, 'city']) + ['photo_url' => $photo]);
            $changed = array_keys($locked->getChanges());

            if ($organisation) {
                $this->organisations->update($organisation, Arr::only($data, [...self::ORGANISATION_FIELDS, 'city']));
                $changed = [...$changed, ...array_map(fn (string $field) => 'organisation.'.$field, array_keys($organisation->getChanges()))];
            }

            $changed = array_values(array_diff($changed, ['updated_at', 'organisation.updated_at']));

            if ($changed !== []) {
                $this->activities->record($locked->getKey(), MemberActivityType::ProfileUpdated, changedFields: $changed);
            }
        });

        return $this->users->withProfile($user->refresh());
    }
}

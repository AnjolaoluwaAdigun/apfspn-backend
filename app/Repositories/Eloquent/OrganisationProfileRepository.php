<?php

namespace App\Repositories\Eloquent;

use App\Models\OrganisationProfile;
use App\Repositories\Contracts\OrganisationProfileRepositoryInterface;

class OrganisationProfileRepository implements OrganisationProfileRepositoryInterface
{
    public function create(array $attributes): OrganisationProfile
    {
        return OrganisationProfile::create($attributes);
    }

    public function findForUserForUpdate(string $userUuid): ?OrganisationProfile
    {
        return OrganisationProfile::where('user_uuid', $userUuid)->lockForUpdate()->first();
    }

    public function update(OrganisationProfile $profile, array $attributes): OrganisationProfile
    {
        $profile->update($attributes);

        return $profile;
    }
}

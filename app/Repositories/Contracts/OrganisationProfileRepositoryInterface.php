<?php

namespace App\Repositories\Contracts;

use App\Models\OrganisationProfile;

interface OrganisationProfileRepositoryInterface
{
    public function create(array $attributes): OrganisationProfile;

    public function findForUserForUpdate(string $userUuid): ?OrganisationProfile;

    public function update(OrganisationProfile $profile, array $attributes): OrganisationProfile;
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\Membership;
use App\Repositories\Contracts\MembershipRepositoryInterface;

class MembershipRepository implements MembershipRepositoryInterface
{
    public function create(array $attributes): Membership
    {
        return Membership::create($attributes);
    }

    public function findWithUser(string $uuid): ?Membership
    {
        return Membership::with('user.organisationProfile')->find($uuid);
    }
}

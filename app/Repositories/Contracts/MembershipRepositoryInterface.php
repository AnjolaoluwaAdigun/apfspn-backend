<?php

namespace App\Repositories\Contracts;

use App\Models\Membership;

interface MembershipRepositoryInterface
{
    public function create(array $attributes): Membership;

    public function findWithUser(string $uuid): ?Membership;
}

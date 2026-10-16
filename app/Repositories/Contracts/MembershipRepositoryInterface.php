<?php

namespace App\Repositories\Contracts;

use App\Models\Membership;

interface MembershipRepositoryInterface
{
    public function create(array $attributes): Membership;

    public function findWithUser(string $uuid): ?Membership;

    public function findByUserForUpdate(string $userUuid): ?Membership;

    public function save(Membership $membership): Membership;

    public function countsByStatus(): array;
}

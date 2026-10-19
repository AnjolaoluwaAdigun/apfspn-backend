<?php

namespace App\Repositories\Contracts;

use App\Models\Membership;
use Illuminate\Support\Carbon;

interface MembershipRepositoryInterface
{
    public function create(array $attributes): Membership;

    public function findWithUser(string $uuid): ?Membership;

    public function findByUserForUpdate(string $userUuid): ?Membership;

    public function findByUser(string $userUuid): ?Membership;

    public function save(Membership $membership): Membership;

    public function countsByStatus(): array;

    public function countCreatedSince(Carbon $since): int;
}

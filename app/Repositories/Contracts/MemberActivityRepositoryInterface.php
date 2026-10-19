<?php

namespace App\Repositories\Contracts;

use App\Models\MemberActivity;
use Illuminate\Support\Collection;

interface MemberActivityRepositoryInterface
{
    public function create(array $attributes): MemberActivity;

    public function latestForUser(string $userUuid, int $limit): Collection;
}

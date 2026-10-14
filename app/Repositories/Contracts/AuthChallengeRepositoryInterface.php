<?php

namespace App\Repositories\Contracts;

use App\Enums\ChallengePurpose;
use App\Models\AuthChallenge;
use Carbon\CarbonInterface;

interface AuthChallengeRepositoryInterface
{
    public function create(array $attributes): AuthChallenge;

    public function findForUpdate(string $uuid): ?AuthChallenge;

    public function latestActiveForUpdate(string $subjectType, string $emailHash, ChallengePurpose $purpose): ?AuthChallenge;

    public function update(AuthChallenge $challenge, array $attributes): AuthChallenge;

    public function countPrunable(CarbonInterface $before): int;

    public function prune(CarbonInterface $before): int;
}

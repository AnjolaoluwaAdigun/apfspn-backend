<?php

namespace App\Repositories\Contracts;

use App\Models\MemberIdSequence;

interface MemberIdSequenceRepositoryInterface
{
    public function findForUpdate(string $stateCode): ?MemberIdSequence;

    public function ensureExists(string $stateCode): void;

    public function increment(MemberIdSequence $sequence): int;
}

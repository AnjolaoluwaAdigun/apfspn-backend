<?php

namespace App\Repositories\Eloquent;

use App\Enums\ChallengePurpose;
use App\Models\AuthChallenge;
use App\Repositories\Contracts\AuthChallengeRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class AuthChallengeRepository implements AuthChallengeRepositoryInterface
{
    public function create(array $attributes): AuthChallenge
    {
        return AuthChallenge::create($attributes);
    }

    public function findForUpdate(string $uuid): ?AuthChallenge
    {
        return AuthChallenge::whereKey($uuid)->lockForUpdate()->first();
    }

    public function latestActiveForUpdate(string $subjectType, string $emailHash, ChallengePurpose $purpose): ?AuthChallenge
    {
        return AuthChallenge::where('subject_type', $subjectType)
            ->where('email_hash', $emailHash)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->lockForUpdate()
            ->first();
    }

    public function update(AuthChallenge $challenge, array $attributes): AuthChallenge
    {
        $challenge->update($attributes);

        return $challenge;
    }

    public function countPrunable(CarbonInterface $before): int
    {
        return $this->prunable($before)->count();
    }

    public function prune(CarbonInterface $before): int
    {
        return $this->prunable($before)->delete();
    }

    private function prunable(CarbonInterface $before): Builder
    {
        return AuthChallenge::where(fn (Builder $query) => $query
            ->where('expires_at', '<', $before)
            ->orWhere('consumed_at', '<', $before));
    }
}

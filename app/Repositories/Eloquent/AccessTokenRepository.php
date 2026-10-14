<?php

namespace App\Repositories\Eloquent;

use App\Models\PersonalAccessToken;
use App\Repositories\Contracts\AccessTokenRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\NewAccessToken;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function create(Model $subject, string $name, array $abilities, CarbonInterface $expiresAt): NewAccessToken
    {
        return $subject->createToken($name, $abilities, $expiresAt);
    }

    public function findForUpdate(string $uuid): ?PersonalAccessToken
    {
        return PersonalAccessToken::whereKey($uuid)->lockForUpdate()->first();
    }

    public function deleteSession(Model $subject, string $sessionName): int
    {
        return $this->forSubject($subject)
            ->whereIn('name', [$sessionName, $sessionName.PersonalAccessToken::REFRESH_SUFFIX])
            ->delete();
    }

    public function deleteClient(Model $subject, string $client): int
    {
        return $this->forSubject($subject)->where('name', 'like', $client.':%')->delete();
    }

    public function deleteAll(Model $subject): int
    {
        return $this->forSubject($subject)->delete();
    }

    public function deleteAllExceptSession(Model $subject, string $sessionName): int
    {
        return $this->forSubject($subject)
            ->whereNotIn('name', [$sessionName, $sessionName.PersonalAccessToken::REFRESH_SUFFIX])
            ->delete();
    }

    private function forSubject(Model $subject): Builder
    {
        return PersonalAccessToken::where('tokenable_type', $subject->getMorphClass())
            ->where('tokenable_id', $subject->getKey());
    }
}

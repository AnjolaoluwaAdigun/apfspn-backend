<?php

namespace App\Repositories\Eloquent;

use App\Models\Membership;
use App\Repositories\Contracts\MembershipRepositoryInterface;
use Illuminate\Support\Carbon;

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

    public function findByUserForUpdate(string $userUuid): ?Membership
    {
        return Membership::where('user_uuid', $userUuid)->whereHas('user')->lockForUpdate()->first();
    }

    public function findByUser(string $userUuid): ?Membership
    {
        return Membership::where('user_uuid', $userUuid)->first();
    }

    public function save(Membership $membership): Membership
    {
        $membership->save();

        return $membership;
    }

    public function countsByStatus(): array
    {
        return Membership::query()
            ->whereHas('user')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function countCreatedSince(Carbon $since): int
    {
        return Membership::query()->whereHas('user')->where('created_at', '>=', $since)->count();
    }
}

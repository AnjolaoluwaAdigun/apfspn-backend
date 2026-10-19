<?php

namespace App\Repositories\Eloquent;

use App\Models\Event;
use App\Models\MemberActivity;
use App\Repositories\Contracts\MemberActivityRepositoryInterface;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

class MemberActivityRepository implements MemberActivityRepositoryInterface
{
    public function create(array $attributes): MemberActivity
    {
        return MemberActivity::create($attributes);
    }

    public function latestForUser(string $userUuid, int $limit): Collection
    {
        return MemberActivity::query()
            ->with(['subject' => fn (MorphTo $morph) => $morph->constrain([
                Event::class => fn ($query) => $query->withTrashed(),
            ])])
            ->where('user_uuid', $userUuid)
            ->orderByDesc('created_at')
            ->orderByDesc('uuid')
            ->limit($limit)
            ->get();
    }
}

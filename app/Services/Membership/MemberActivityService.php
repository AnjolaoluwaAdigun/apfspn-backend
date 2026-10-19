<?php

namespace App\Services\Membership;

use App\Enums\MemberActivityType;
use App\Models\MemberActivity;
use App\Repositories\Contracts\MemberActivityRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class MemberActivityService
{
    public const DASHBOARD_LIMIT = 10;

    private const IGNORED_FIELDS = ['updated_at', 'updated_by_admin_uuid'];

    public function __construct(private readonly MemberActivityRepositoryInterface $activities) {}

    public function record(string $userUuid, MemberActivityType $type, ?Model $subject = null, array $changedFields = []): MemberActivity
    {
        $changedFields = array_values(array_diff($changedFields, self::IGNORED_FIELDS));

        return $this->activities->create([
            'user_uuid' => $userUuid,
            'type' => $type,
            'subject_type' => $subject?->getMorphClass(),
            'subject_uuid' => $subject?->getKey(),
            'properties' => $changedFields === [] ? null : ['changed_fields' => $changedFields],
        ]);
    }

    public function latest(string $userUuid): Collection
    {
        return $this->activities->latestForUser($userUuid, self::DASHBOARD_LIMIT);
    }
}

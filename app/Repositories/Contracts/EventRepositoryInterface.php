<?php

namespace App\Repositories\Contracts;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface EventRepositoryInterface
{
    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator;

    public function paginatePublic(array $filters, int $perPage): LengthAwarePaginator;

    public function findWithSpeakers(string $uuid): Event;

    public function findPublicBySlug(string $slug): Event;

    public function findForUpdate(string $uuid): ?Event;

    public function slugExists(string $slug, ?string $ignoreUuid = null): bool;

    public function uuidsEndedBefore(Carbon $moment): Collection;

    public function upcomingPublished(int $limit): Collection;

    public function countCreatedBy(string $adminUuid): int;

    public function create(array $attributes): Event;

    public function save(Event $event): Event;

    public function delete(Event $event): void;
}

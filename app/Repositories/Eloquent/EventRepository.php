<?php

namespace App\Repositories\Eloquent;

use App\Enums\EventStatus;
use App\Enums\EventTimeFilter;
use App\Models\Event;
use App\Repositories\Contracts\EventRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EventRepository implements EventRepositoryInterface
{
    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->searched(Event::query(), $filters['search'] ?? null)
            ->with('speakers')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['audience'] ?? null, fn (Builder $query, string $audience) => $query->where('audience', $audience))
            ->when($filters['starts_from'] ?? null, fn (Builder $query, string $date) => $query->where('starts_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['starts_to'] ?? null, fn (Builder $query, string $date) => $query->where('starts_at', '<=', Carbon::parse($date)->endOfDay()))
            ->orderByDesc('starts_at')
            ->orderByDesc('uuid')
            ->paginate($perPage);
    }

    public function paginatePublic(array $filters, int $perPage): LengthAwarePaginator
    {
        $past = ($filters['when'] ?? null) === EventTimeFilter::Past->value;

        return $this->searched(Event::query(), $filters['search'] ?? null)
            ->with('speakers')
            ->whereIn('status', EventStatus::publiclyVisible())
            ->when($filters['when'] ?? null, fn (Builder $query) => $past
                ? $query->where('ends_at', '<', now())
                : $query->where('ends_at', '>=', now()))
            ->orderBy('starts_at', $past ? 'desc' : 'asc')
            ->orderBy('uuid')
            ->paginate($perPage);
    }

    public function findWithSpeakers(string $uuid): Event
    {
        return Event::with('speakers')->findOrFail($uuid);
    }

    public function findPublicBySlug(string $slug): Event
    {
        return Event::with('speakers')
            ->where('slug', $slug)
            ->whereIn('status', EventStatus::publiclyVisible())
            ->firstOrFail();
    }

    public function findForUpdate(string $uuid): ?Event
    {
        return Event::whereKey($uuid)->lockForUpdate()->first();
    }

    public function slugExists(string $slug, ?string $ignoreUuid = null): bool
    {
        return Event::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreUuid, fn (Builder $query, string $uuid) => $query->whereKeyNot($uuid))
            ->exists();
    }

    public function uuidsEndedBefore(Carbon $moment): Collection
    {
        return Event::where('status', EventStatus::Published)
            ->where('ends_at', '<', $moment)
            ->orderBy('ends_at')
            ->pluck('uuid');
    }

    public function upcomingPublished(int $limit): Collection
    {
        return Event::query()
            ->where('status', EventStatus::Published)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->orderBy('uuid')
            ->limit($limit)
            ->get();
    }

    public function countCreatedBy(string $adminUuid): int
    {
        return Event::withTrashed()->where('created_by_admin_uuid', $adminUuid)->count();
    }

    public function create(array $attributes): Event
    {
        return Event::create($attributes);
    }

    public function save(Event $event): Event
    {
        $event->save();

        return $event;
    }

    public function delete(Event $event): void
    {
        $event->delete();
    }

    private function searched(Builder $query, ?string $search): Builder
    {
        if (! $search) {
            return $query;
        }

        $term = '%'.addcslashes($search, '\%_').'%';

        return $query->where(fn (Builder $inner) => $inner
            ->where('title', 'like', $term)
            ->orWhere('venue', 'like', $term)
            ->orWhere('city', 'like', $term));
    }
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function create(array $attributes): AuditLog
    {
        return AuditLog::create($attributes);
    }

    public function paginateForAdmin(string $adminUuid, int $perPage): LengthAwarePaginator
    {
        return AuditLog::where('admin_uuid', $adminUuid)
            ->orderByDesc('created_at')
            ->orderByDesc('uuid')
            ->paginate($perPage);
    }

    public function countForAdmin(string $adminUuid): int
    {
        return AuditLog::where('admin_uuid', $adminUuid)->count();
    }

    public function latest(int $limit): Collection
    {
        return AuditLog::query()
            ->with([
                'admin',
                'auditable' => fn (MorphTo $morph) => $morph
                    ->constrain([
                        User::class => fn ($query) => $query->withTrashed(),
                        Event::class => fn ($query) => $query->withTrashed(),
                        Article::class => fn ($query) => $query->withTrashed(),
                    ])
                    ->morphWith([
                        Membership::class => ['user'],
                        EventRegistration::class => ['user', 'event'],
                    ]),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('uuid')
            ->limit($limit)
            ->get();
    }
}

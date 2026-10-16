<?php

namespace App\Repositories\Eloquent;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
}

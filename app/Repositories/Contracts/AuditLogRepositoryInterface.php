<?php

namespace App\Repositories\Contracts;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AuditLogRepositoryInterface
{
    public function create(array $attributes): AuditLog;

    public function paginateForAdmin(string $adminUuid, int $perPage): LengthAwarePaginator;

    public function countForAdmin(string $adminUuid): int;
}

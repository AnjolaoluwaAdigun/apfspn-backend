<?php

namespace App\Repositories\Contracts;

use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RoleRepositoryInterface
{
    public function paginateWithPermissions(int $perPage): LengthAwarePaginator;

    public function findWithPermissions(string $uuid): Role;

    public function findForUpdate(string $uuid): ?Role;

    public function findByUuid(string $uuid): ?Role;

    public function create(string $name, string $guard): Role;

    public function save(Role $role): Role;

    public function adminCount(Role $role): int;

    public function delete(Role $role): void;

    public function permissionNames(Role $role): array;

    public function syncPermissions(Role $role, array $permissions): void;
}

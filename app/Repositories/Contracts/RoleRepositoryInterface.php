<?php

namespace App\Repositories\Contracts;

use App\Models\Role;
use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    public function allWithPermissions(): Collection;

    public function findWithPermissions(string $uuid): Role;

    public function findForUpdate(string $uuid): ?Role;

    public function findByUuid(string $uuid): ?Role;

    public function create(string $name, string $guard): Role;

    public function save(Role $role): Role;

    public function adminCount(Role $role): int;

    public function delete(Role $role): void;
}

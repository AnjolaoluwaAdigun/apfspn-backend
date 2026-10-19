<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RoleRepository implements RoleRepositoryInterface
{
    public function paginateWithPermissions(int $perPage): LengthAwarePaginator
    {
        return Role::with('permissions')->withCount('admins')->orderBy('name')->orderBy('uuid')->paginate($perPage);
    }

    public function findWithPermissions(string $uuid): Role
    {
        return Role::with('permissions')->withCount('admins')->findOrFail($uuid);
    }

    public function findForUpdate(string $uuid): ?Role
    {
        return Role::whereKey($uuid)->lockForUpdate()->first();
    }

    public function findByUuid(string $uuid): ?Role
    {
        return Role::find($uuid);
    }

    public function create(string $name, string $guard): Role
    {
        return Role::create(['name' => $name, 'guard_name' => $guard]);
    }

    public function save(Role $role): Role
    {
        $role->save();

        return $role;
    }

    public function adminCount(Role $role): int
    {
        return $role->admins()->count();
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    public function permissionNames(Role $role): array
    {
        return $role->permissions()->pluck('name')->sort()->values()->all();
    }

    public function syncPermissions(Role $role, array $permissions): void
    {
        $role->syncPermissions($permissions);
    }
}

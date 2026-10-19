<?php

namespace App\Repositories\Contracts;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AdminRepositoryInterface extends AuthSubjectRepositoryInterface
{
    public function findByEmail(string $email): ?Admin;

    public function findByUuid(string $uuid): ?Admin;

    public function findForUpdate(string $uuid): ?Admin;

    public function create(array $attributes): Admin;

    public function update(Admin $admin, array $attributes): Admin;

    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    public function findWithRoles(string $uuid): Admin;

    public function withRoleForUpdate(string $role): Collection;

    public function save(Admin $admin): Admin;

    public function delete(Admin $admin): void;

    public function currentRole(Admin $admin): ?Role;

    public function syncRoles(Admin $admin, array $roleNames): void;

    public function permissionNames(Admin $admin): array;
}

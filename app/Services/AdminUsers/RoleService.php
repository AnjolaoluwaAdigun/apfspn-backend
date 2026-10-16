<?php

namespace App\Services\AdminUsers;

use App\Enums\ePermission;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleService
{
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly PrivilegeGuard $privileges,
        private readonly AuditLogService $audit,
    ) {}

    public static function isBuiltIn(string $name): bool
    {
        return array_key_exists($name, RolesAndPermissionsSeeder::roleMap());
    }

    public static function isLocked(string $name): bool
    {
        return $name === AdminUserService::SUPER_ADMIN_ROLE;
    }

    public function list(): Collection
    {
        return $this->roles->allWithPermissions();
    }

    public function show(string $uuid): Role
    {
        return $this->roles->findWithPermissions($uuid);
    }

    public function permissions(): array
    {
        return collect(ePermission::cases())
            ->groupBy(fn (ePermission $permission) => Str::before($permission->value, '.'))
            ->map(fn (Collection $permissions, string $module) => [
                'module' => $module,
                'label' => Str::headline($module),
                'permissions' => $permissions->map(fn (ePermission $permission) => [
                    'name' => $permission->value,
                    'label' => $permission->label(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    public function create(array $data, Admin $actor): Role
    {
        $this->privileges->assertCanGrant($actor, $data['permissions']);

        $uuid = DB::transaction(function () use ($data) {
            $role = $this->roles->create($data['name'], RolesAndPermissionsSeeder::GUARD);
            $role->syncPermissions($data['permissions']);
            $this->audit->record('role.created', $role);

            return $role->getKey();
        }, self::TRANSACTION_ATTEMPTS);

        return $this->show($uuid);
    }

    public function update(string $uuid, array $data, Admin $actor): Role
    {
        DB::transaction(function () use ($uuid, $data, $actor) {
            $role = $this->locked($uuid);
            $current = $role->permissions()->pluck('name')->sort()->values()->all();

            if (isset($data['name']) && $data['name'] !== $role->name) {
                if (self::isBuiltIn($role->name)) {
                    throw new ApiException('Built-in roles cannot be renamed.', 422, ['code' => 'built_in_role_name_locked']);
                }

                $role->name = $data['name'];
            }

            $changes = $this->audit->changeSet($role);
            $permissions = null;

            if (array_key_exists('permissions', $data)) {
                $permissions = collect($data['permissions'])->unique()->sort()->values()->all();
                $this->privileges->assertCanGrant($actor, array_values(array_diff($permissions, $current)));

                if ($permissions !== $current) {
                    $changes['changed_fields'][] = 'permissions';
                    $changes['previous']['permissions'] = $current;
                }
            }

            if ($changes['changed_fields'] === []) {
                return;
            }

            $this->roles->save($role);

            if ($permissions !== null) {
                $role->syncPermissions($permissions);
            }

            $this->audit->record('role.updated', $role, $changes);
        }, self::TRANSACTION_ATTEMPTS);

        return $this->show($uuid);
    }

    public function delete(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $role = $this->locked($uuid);
            $admins = $this->roles->adminCount($role);

            if ($admins > 0) {
                throw ApiException::blocked('This role is assigned to admins and cannot be deleted.', ['admins' => $admins]);
            }

            $this->audit->record('role.deleted', $role);
            $this->roles->delete($role);
        }, self::TRANSACTION_ATTEMPTS);
    }

    private function locked(string $uuid): Role
    {
        $role = $this->roles->findForUpdate($uuid)
            ?? throw (new ModelNotFoundException)->setModel(Role::class, [$uuid]);

        if (self::isLocked($role->name)) {
            throw new ApiException('The super admin role cannot be changed.', 422, ['code' => 'role_locked']);
        }

        return $role;
    }
}

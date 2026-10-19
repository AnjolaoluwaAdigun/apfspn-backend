<?php

namespace App\Services\AdminUsers;

use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\Role;
use App\Repositories\Contracts\AdminRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;

class PrivilegeGuard
{
    public function __construct(
        private readonly AdminRepositoryInterface $admins,
        private readonly RoleRepositoryInterface $roles,
    ) {}

    public function assertCanGrant(Admin $actor, array $permissions): void
    {
        $missing = array_values(array_diff($permissions, $this->permissionsOf($actor)));

        if ($missing !== []) {
            throw new ApiException('You cannot grant permissions you do not hold yourself.', 403, [
                'code' => 'role_exceeds_own_permissions',
                'permissions' => $missing,
            ]);
        }
    }

    public function assertCanGrantRole(Admin $actor, Role $role): void
    {
        $this->assertCanGrant($actor, $this->roles->permissionNames($role));
    }

    public function assertCanManage(Admin $actor, Admin $target): void
    {
        $this->assertCanGrant($actor, $this->permissionsOf($target));
    }

    private function permissionsOf(Admin $admin): array
    {
        return $this->admins->permissionNames($admin);
    }
}

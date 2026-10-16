<?php

namespace App\Services\AdminUsers;

use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\Role;

class PrivilegeGuard
{
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
        $this->assertCanGrant($actor, $role->permissions()->pluck('name')->all());
    }

    public function assertCanManage(Admin $actor, Admin $target): void
    {
        $this->assertCanGrant($actor, $this->permissionsOf($target));
    }

    private function permissionsOf(Admin $admin): array
    {
        return $admin->getAllPermissions()->pluck('name')->all();
    }
}

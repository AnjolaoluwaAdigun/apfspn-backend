<?php

namespace Tests\Feature\AdminUsers;

use App\Models\Admin;
use App\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;

trait InteractsWithAdminUsers
{
    private array $adminTokenCache = [];

    protected function adminWithRole(string $role, array $attributes = []): Admin
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return tap(Admin::factory()->create($attributes))->assignRole($role);
    }

    protected function tokenFor(Admin $admin): string
    {
        return $this->adminTokenCache[$admin->uuid] ??= $this->adminTokens($admin)['access_token'];
    }

    protected function role(string $name): Role
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return Role::where('name', $name)->firstOrFail();
    }

    protected function userUrl(Admin $admin, string $suffix = ''): string
    {
        return '/api/v1/admin/users/'.$admin->uuid.($suffix !== '' ? '/'.$suffix : '');
    }

    protected function roleUrl(Role $role): string
    {
        return '/api/v1/admin/roles/'.$role->uuid;
    }
}

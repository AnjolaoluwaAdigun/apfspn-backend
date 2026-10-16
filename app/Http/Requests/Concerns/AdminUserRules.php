<?php

namespace App\Http\Requests\Concerns;

use App\Enums\AdminAccountState;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\Rule;

trait AdminUserRules
{
    private const PROHIBITED_ADMIN_FIELDS = [
        'uuid', 'password', 'status', 'must_reset_password', 'roles', 'role', 'permissions', 'last_login_at',
        'created_by_admin_uuid', 'updated_by_admin_uuid',
    ];

    protected function prohibitedAdminRules(): array
    {
        return array_fill_keys(self::PROHIBITED_ADMIN_FIELDS, ['prohibited']);
    }

    protected function roleUuidRules(array $presence): array
    {
        return ['role_uuid' => [...$presence, 'uuid', Rule::exists('roles', 'uuid')->where('guard_name', RolesAndPermissionsSeeder::GUARD)]];
    }

    protected function adminFilterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'account_state' => ['nullable', Rule::enum(AdminAccountState::class)],
            'role_uuid' => ['nullable', 'uuid'],
        ];
    }
}

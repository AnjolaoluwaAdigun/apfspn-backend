<?php

namespace App\Http\Requests\Concerns;

use App\Enums\ePermission;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\Rule;

trait RoleRules
{
    protected function roleRules(?string $roleUuid = null): array
    {
        $required = $roleUuid !== null ? ['sometimes', 'required'] : ['required'];

        return [
            'uuid' => ['prohibited'],
            'guard_name' => ['prohibited'],
            'name' => [
                ...$required, 'string', 'regex:/^[a-z][a-z0-9_]{2,49}$/',
                Rule::unique('roles', 'name')->where('guard_name', RolesAndPermissionsSeeder::GUARD)->ignore($roleUuid, 'uuid'),
            ],
            'permissions' => [...$required, 'array', 'min:1'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::enum(ePermission::class)],
        ];
    }
}

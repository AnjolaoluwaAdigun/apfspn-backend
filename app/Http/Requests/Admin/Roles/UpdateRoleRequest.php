<?php

namespace App\Http\Requests\Admin\Roles;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\RoleRules;

class UpdateRoleRequest extends ApiFormRequest
{
    use RoleRules;

    public function rules(): array
    {
        return $this->roleRules($this->route('role')->getKey());
    }
}

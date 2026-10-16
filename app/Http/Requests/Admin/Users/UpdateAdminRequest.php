<?php

namespace App\Http\Requests\Admin\Users;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\AdminUserRules;

class UpdateAdminRequest extends ApiFormRequest
{
    use AdminUserRules;

    public function rules(): array
    {
        return array_merge(
            $this->prohibitedAdminRules(),
            $this->roleUuidRules(['sometimes', 'required']),
            ['name' => ['sometimes', 'required', 'string', 'max:200'], 'email' => ['prohibited']],
        );
    }
}

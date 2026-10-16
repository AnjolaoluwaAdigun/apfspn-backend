<?php

namespace App\Http\Requests\Admin\Users;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\AdminUserRules;

class ResendInviteRequest extends ApiFormRequest
{
    use AdminUserRules;

    public function rules(): array
    {
        return $this->prohibitedAdminRules();
    }
}

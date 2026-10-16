<?php

namespace App\Http\Requests\Admin\Users;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\AdminUserRules;
use App\Http\Requests\Concerns\PaginationRules;

class ListAdminsRequest extends ApiFormRequest
{
    use AdminUserRules, PaginationRules;

    public function rules(): array
    {
        return array_merge($this->adminFilterRules(), $this->paginationRules());
    }
}

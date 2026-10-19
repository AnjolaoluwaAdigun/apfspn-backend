<?php

namespace App\Http\Requests\Admin\Roles;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\PaginationRules;

class ListRolesRequest extends ApiFormRequest
{
    use PaginationRules;

    public function rules(): array
    {
        return $this->paginationRules();
    }
}

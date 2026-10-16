<?php

namespace App\Http\Requests\Admin\Users;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\PaginationRules;

class AdminAuditLogRequest extends ApiFormRequest
{
    use PaginationRules;

    public function rules(): array
    {
        return $this->paginationRules();
    }
}

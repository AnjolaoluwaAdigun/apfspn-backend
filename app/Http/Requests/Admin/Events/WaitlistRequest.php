<?php

namespace App\Http\Requests\Admin\Events;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\PaginationRules;

class WaitlistRequest extends ApiFormRequest
{
    use PaginationRules;

    public function rules(): array
    {
        return $this->paginationRules();
    }
}

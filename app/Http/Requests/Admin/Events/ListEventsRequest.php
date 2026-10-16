<?php

namespace App\Http\Requests\Admin\Events;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\EventRules;
use App\Http\Requests\Concerns\PaginationRules;

class ListEventsRequest extends ApiFormRequest
{
    use EventRules, PaginationRules;

    public function rules(): array
    {
        return array_merge($this->eventFilterRules(), $this->paginationRules());
    }
}

<?php

namespace App\Http\Requests\Admin\Events;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\AttendeeFilterRules;
use App\Http\Requests\Concerns\PaginationRules;

class ListAttendeesRequest extends ApiFormRequest
{
    use AttendeeFilterRules, PaginationRules;

    public function rules(): array
    {
        return array_merge($this->attendeeFilterRules(), $this->paginationRules());
    }
}

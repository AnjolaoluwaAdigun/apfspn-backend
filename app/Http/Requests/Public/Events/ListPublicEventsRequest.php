<?php

namespace App\Http\Requests\Public\Events;

use App\Enums\EventTimeFilter;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\PaginationRules;
use Illuminate\Validation\Rule;

class ListPublicEventsRequest extends ApiFormRequest
{
    use PaginationRules;

    public function rules(): array
    {
        return array_merge([
            'when' => ['nullable', Rule::enum(EventTimeFilter::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ], $this->paginationRules());
    }
}

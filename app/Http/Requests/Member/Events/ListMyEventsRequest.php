<?php

namespace App\Http\Requests\Member\Events;

use App\Enums\MyEventsFilter;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\PaginationRules;
use Illuminate\Validation\Rule;

class ListMyEventsRequest extends ApiFormRequest
{
    use PaginationRules;

    public function rules(): array
    {
        return array_merge([
            'filter' => ['nullable', Rule::enum(MyEventsFilter::class)],
        ], $this->paginationRules());
    }
}

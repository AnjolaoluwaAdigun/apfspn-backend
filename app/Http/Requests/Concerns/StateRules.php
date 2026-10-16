<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait StateRules
{
    protected function stateRules(): array
    {
        return [
            'state_code' => ['required', 'string', Rule::in(array_keys(config('apfspn.states')))],
            'city' => ['nullable', 'string', 'max:100'],
        ];
    }
}

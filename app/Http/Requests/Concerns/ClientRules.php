<?php

namespace App\Http\Requests\Concerns;

use App\Enums\AuthClient;
use Illuminate\Validation\Rule;

trait ClientRules
{
    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing(['client' => AuthClient::Web->value]);
    }

    protected function clientRules(): array
    {
        return ['client' => ['required', Rule::enum(AuthClient::class)]];
    }
}

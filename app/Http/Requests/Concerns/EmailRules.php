<?php

namespace App\Http\Requests\Concerns;

trait EmailRules
{
    protected function emailRules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255']];
    }
}

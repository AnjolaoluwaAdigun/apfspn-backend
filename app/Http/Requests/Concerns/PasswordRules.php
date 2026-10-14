<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rules\Password;

trait PasswordRules
{
    protected function newPasswordRules(): array
    {
        return ['password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()]];
    }
}

<?php

namespace App\Http\Requests\Admin\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\PasswordRules;

class ResetPasswordRequest extends ApiFormRequest
{
    use PasswordRules;

    public function rules(): array
    {
        return $this->newPasswordRules() + [
            'reset_token' => ['required', 'string', 'max:2048'],
        ];
    }
}

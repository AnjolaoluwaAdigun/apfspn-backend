<?php

namespace App\Http\Requests\Member\Registration;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ClientRules;
use App\Http\Requests\Concerns\PasswordRules;

class CreatePasswordRequest extends ApiFormRequest
{
    use ClientRules, PasswordRules;

    public function rules(): array
    {
        return $this->newPasswordRules() + $this->clientRules() + [
            'password_token' => ['required', 'string', 'max:2048'],
        ];
    }
}

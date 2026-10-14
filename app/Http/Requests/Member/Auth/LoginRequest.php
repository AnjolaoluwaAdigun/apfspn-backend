<?php

namespace App\Http\Requests\Member\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ClientRules;
use App\Http\Requests\Concerns\EmailRules;

class LoginRequest extends ApiFormRequest
{
    use ClientRules, EmailRules;

    public function rules(): array
    {
        return $this->emailRules() + $this->clientRules() + [
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}

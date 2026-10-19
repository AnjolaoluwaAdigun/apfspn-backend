<?php

namespace App\Http\Requests\Admin\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\AccountFieldRules;
use App\Http\Requests\Concerns\PasswordRules;

class ResetPasswordRequest extends ApiFormRequest
{
    use AccountFieldRules, PasswordRules;

    public function rules(): array
    {
        return $this->newPasswordRules() + [
            'reset_token' => ['required', 'string', 'max:2048'],
        ] + $this->prohibitedAdminAccountRules();
    }
}

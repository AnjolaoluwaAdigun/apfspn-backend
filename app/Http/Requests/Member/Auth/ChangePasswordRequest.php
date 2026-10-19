<?php

namespace App\Http\Requests\Member\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\AccountFieldRules;
use App\Http\Requests\Concerns\PasswordRules;

class ChangePasswordRequest extends ApiFormRequest
{
    use AccountFieldRules, PasswordRules;

    public function rules(): array
    {
        return $this->newPasswordRules() + [
            'current_password' => ['required', 'string', 'max:255'],
        ] + $this->prohibitedMemberAccountRules(['password']);
    }
}

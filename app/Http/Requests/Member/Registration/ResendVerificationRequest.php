<?php

namespace App\Http\Requests\Member\Registration;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\EmailRules;

class ResendVerificationRequest extends ApiFormRequest
{
    use EmailRules;

    public function rules(): array
    {
        return $this->emailRules();
    }
}

<?php

namespace App\Http\Requests\Member\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ChallengeRules;

class ResendOtpRequest extends ApiFormRequest
{
    use ChallengeRules;

    public function rules(): array
    {
        return $this->challengeTokenRules();
    }
}

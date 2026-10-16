<?php

namespace App\Http\Requests\Member\Registration;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ChallengeRules;

class VerifyEmailRequest extends ApiFormRequest
{
    use ChallengeRules;

    public function rules(): array
    {
        return $this->challengeRules();
    }
}

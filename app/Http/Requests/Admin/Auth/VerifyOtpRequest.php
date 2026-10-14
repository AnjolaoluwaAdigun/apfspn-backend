<?php

namespace App\Http\Requests\Admin\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ChallengeRules;
use App\Http\Requests\Concerns\ClientRules;

class VerifyOtpRequest extends ApiFormRequest
{
    use ChallengeRules, ClientRules;

    public function rules(): array
    {
        return $this->challengeRules() + $this->clientRules();
    }
}

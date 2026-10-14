<?php

namespace App\Http\Requests\Concerns;

trait ChallengeRules
{
    protected function challengeTokenRules(): array
    {
        return ['challenge_token' => ['required', 'string', 'max:2048']];
    }

    protected function challengeRules(): array
    {
        return $this->challengeTokenRules() + [
            'code' => ['required', 'string', 'digits:'.config('apfspn.auth.otp_length')],
        ];
    }
}

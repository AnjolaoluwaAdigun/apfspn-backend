<?php

namespace App\Console\Commands;

use App\Services\Membership\RegistrationService;
use Illuminate\Console\Command;

class IssueMemberVerifyCode extends Command
{
    protected $signature = 'apfspn:member-verify-code {email : Email of the member}';

    protected $description = 'Local only: issue and print a fresh email verification code and challenge token for a member, without sending email';

    public function handle(RegistrationService $registration): int
    {
        if (! $this->laravel->environment('local')) {
            $this->error('This command only runs when APP_ENV=local.');

            return self::FAILURE;
        }

        $result = $registration->reissueVerificationCode($this->argument('email'));

        if (! $result) {
            $this->error('No member awaiting email verification was found with that email.');

            return self::FAILURE;
        }

        $this->line('code: '.$result['code']);
        $this->line('challenge_token: '.$result['challenge']['challenge_token']);

        return self::SUCCESS;
    }
}

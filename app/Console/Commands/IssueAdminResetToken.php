<?php

namespace App\Console\Commands;

use App\Services\Auth\AdminAuthService;
use Illuminate\Console\Command;

class IssueAdminResetToken extends Command
{
    protected $signature = 'apfspn:admin-reset-token {email : Email of the admin}';

    protected $description = 'Local only: print a fresh password reset token for an admin';

    public function handle(AdminAuthService $auth): int
    {
        if (! $this->laravel->environment('local')) {
            $this->error('This command only runs when APP_ENV=local.');

            return self::FAILURE;
        }

        $token = $auth->issueResetToken($this->argument('email'));

        if (! $token) {
            $this->error('No admin found with that email.');

            return self::FAILURE;
        }

        $this->line($token);

        return self::SUCCESS;
    }
}

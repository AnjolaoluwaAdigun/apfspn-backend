<?php

namespace App\Services\Auth;

use App\Enums\AuthClient;
use App\Enums\ChallengePurpose;
use App\Enums\TokenAudience;
use App\Models\Admin;
use App\Models\PersonalAccessToken;
use App\Repositories\Contracts\AdminRepositoryInterface;

class AdminAuthService
{
    public function __construct(
        private readonly AdminRepositoryInterface $admins,
        private readonly CredentialVerifier $credentials,
        private readonly TokenService $tokens,
        private readonly ChallengeService $challenges,
        private readonly PasswordResetService $resets,
    ) {}

    public function login(string $email, string $password, AuthClient $client): array
    {
        $admin = $this->admins->findByEmail($email);
        $this->credentials->assertValid($admin, $password);
        $this->tokens->assertCanAuthenticate($admin);

        if (config('apfspn.auth.admin_two_factor')) {
            return [
                'next_step' => 'verify_otp',
                'challenge' => $this->challenges->create($admin, Admin::class, ChallengePurpose::AdminLogin, $admin->email),
            ];
        }

        return $this->authenticate($admin, $client);
    }

    public function verifyOtp(string $token, string $code, AuthClient $client): array
    {
        $challenge = $this->challenges->verify($token, $code, [ChallengePurpose::AdminLogin, ChallengePurpose::PasswordReset], Admin::class);
        $admin = $challenge->subject;

        if ($challenge->purpose === ChallengePurpose::PasswordReset) {
            return [
                'next_step' => 'reset_password',
                'reset_token' => $this->resets->issueToken($admin, config('apfspn.auth.reset_token_ttl_minutes')),
            ];
        }

        $this->tokens->assertCanAuthenticate($admin);

        return $this->authenticate($admin, $client);
    }

    public function resendOtp(string $token): array
    {
        return $this->challenges->resend($token, Admin::class);
    }

    public function forgotPassword(string $email): array
    {
        return $this->challenges->create($this->admins->findByEmail($email), Admin::class, ChallengePurpose::PasswordReset, $email);
    }

    public function issueResetToken(string $email): ?string
    {
        $admin = $this->admins->findByEmail($email);

        return $admin ? $this->resets->issueToken($admin, config('apfspn.auth.reset_token_ttl_minutes')) : null;
    }

    public function resetPassword(string $resetToken, string $password): void
    {
        $this->resets->reset($resetToken, $password, $this->admins);
    }

    public function refresh(PersonalAccessToken $refreshToken): array
    {
        return $this->tokens->rotate($refreshToken, TokenAudience::Admin);
    }

    public function logout(PersonalAccessToken $token): void
    {
        $this->tokens->revokeSession($token);
    }

    public function me(Admin $admin): Admin
    {
        return $admin->load('roles');
    }

    private function authenticate(Admin $admin, AuthClient $client): array
    {
        $this->admins->update($admin, ['last_login_at' => now()]);

        return [
            'next_step' => 'authenticated',
            'tokens' => $this->tokens->issue($admin, $client, TokenAudience::Admin),
            'admin' => $admin->load('roles'),
        ];
    }
}

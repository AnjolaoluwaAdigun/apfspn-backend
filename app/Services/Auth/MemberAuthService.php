<?php

namespace App\Services\Auth;

use App\Enums\AuthClient;
use App\Enums\ChallengePurpose;
use App\Enums\TokenAudience;
use App\Exceptions\ApiException;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MemberAuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly CredentialVerifier $credentials,
        private readonly TokenService $tokens,
        private readonly ChallengeService $challenges,
        private readonly PasswordResetService $resets,
    ) {}

    public function login(string $email, string $password, AuthClient $client): array
    {
        $user = $this->users->findByEmail($email);
        $this->credentials->assertValid($user, $password);
        $this->tokens->assertCanAuthenticate($user);

        if ($user->two_factor_enabled) {
            return [
                'next_step' => 'verify_otp',
                'challenge' => $this->challenges->create($user, User::class, ChallengePurpose::MemberLogin, $user->email),
            ];
        }

        return $this->authenticate($user, $client);
    }

    public function verifyLoginOtp(string $token, string $code, AuthClient $client): array
    {
        $user = $this->challenges->verify($token, $code, [ChallengePurpose::MemberLogin], User::class)->subject;
        $this->tokens->assertCanAuthenticate($user);

        return $this->authenticate($user, $client);
    }

    public function resendOtp(string $token): array
    {
        return $this->challenges->resend($token, User::class);
    }

    public function forgotPassword(string $email): array
    {
        return $this->challenges->create($this->users->findByEmail($email), User::class, ChallengePurpose::PasswordReset, $email);
    }

    public function verifyResetOtp(string $token, string $code): array
    {
        $user = $this->challenges->verify($token, $code, [ChallengePurpose::PasswordReset], User::class)->subject;

        return [
            'next_step' => 'reset_password',
            'reset_token' => $this->resets->issueToken($user, config('apfspn.auth.reset_token_ttl_minutes')),
        ];
    }

    public function resetPassword(string $resetToken, string $password): void
    {
        $this->resets->reset($resetToken, $password, $this->users);
    }

    public function changePassword(User $user, PersonalAccessToken $current, string $currentPassword, string $password): void
    {
        DB::transaction(function () use ($user, $current, $currentPassword, $password) {
            $locked = $this->users->findForUpdate($user->getKey());

            if (! Hash::check($currentPassword, $locked->password)) {
                throw new ApiException('Your current password is incorrect.', 422, ['code' => 'invalid_current_password']);
            }

            $this->users->update($locked, ['password' => $password]);
            $this->tokens->revokeOthers($locked, $current);
        });
    }

    public function refresh(PersonalAccessToken $refreshToken): array
    {
        return $this->tokens->rotate($refreshToken, TokenAudience::Member);
    }

    public function me(User $user): User
    {
        return $this->users->withProfile($user);
    }

    public function logout(PersonalAccessToken $token): void
    {
        $this->tokens->revokeSession($token);
    }

    private function authenticate(User $user, AuthClient $client): array
    {
        $this->users->update($user, ['last_login_at' => now()]);

        return [
            'next_step' => 'authenticated',
            'tokens' => $this->tokens->issue($user, $client, TokenAudience::Member),
            'member' => $this->users->withProfile($user),
        ];
    }
}

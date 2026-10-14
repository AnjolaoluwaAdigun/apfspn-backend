<?php

namespace App\Services\Auth;

use App\Enums\AuthClient;
use App\Enums\TokenAudience;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Repositories\Contracts\AccessTokenRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TokenService
{
    public function __construct(private readonly AccessTokenRepositoryInterface $tokens) {}

    public function issue(Admin|User $subject, AuthClient $client, TokenAudience $audience, ?string $session = null): array
    {
        $name = $client->value.':'.($session ?? Str::lower(Str::random(20)));
        $accessTtl = config('apfspn.auth.access_ttl_minutes') * 60;
        $refreshTtl = config('apfspn.auth.refresh_ttl_days') * 86400;

        $access = $this->tokens->create($subject, $name, [$audience->value], now()->addSeconds($accessTtl));
        $refresh = $this->tokens->create($subject, $name.PersonalAccessToken::REFRESH_SUFFIX, [$audience->value.':refresh'], now()->addSeconds($refreshTtl));

        return [
            'access_token' => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTtl,
            'refresh_expires_in' => $refreshTtl,
        ];
    }

    public function rotate(PersonalAccessToken $refreshToken, TokenAudience $audience): array
    {
        return DB::transaction(function () use ($refreshToken, $audience) {
            $locked = $this->tokens->findForUpdate($refreshToken->getKey());

            if (! $locked || ! $locked->isRefresh()) {
                throw new ApiException('This refresh token is no longer valid.', 401, ['code' => 'invalid_refresh_token']);
            }

            $subject = $locked->tokenable;
            $this->assertCanAuthenticate($subject);

            [$client, $session] = explode(':', $locked->sessionName(), 2);
            $this->tokens->deleteSession($subject, $locked->sessionName());

            return $this->issue($subject, AuthClient::from($client), $audience, $session);
        });
    }

    public function revokeSession(PersonalAccessToken $token): void
    {
        $this->tokens->deleteSession($token->tokenable, $token->sessionName());
    }

    public function revokeClient(Admin|User $subject, AuthClient $client): void
    {
        $this->tokens->deleteClient($subject, $client->value);
    }

    public function revokeAll(Admin|User $subject): void
    {
        $this->tokens->deleteAll($subject);
    }

    public function revokeOthers(Admin|User $subject, PersonalAccessToken $current): void
    {
        $this->tokens->deleteAllExceptSession($subject, $current->sessionName());
    }

    public function assertCanAuthenticate(Admin|User $subject): void
    {
        if (! $subject->isActive()) {
            throw new ApiException('This account has been disabled.', 403, ['code' => 'account_disabled']);
        }

        if ($subject instanceof Admin && $subject->must_reset_password) {
            throw new ApiException('You must reset your password before signing in.', 403, ['code' => 'must_reset_password']);
        }
    }
}

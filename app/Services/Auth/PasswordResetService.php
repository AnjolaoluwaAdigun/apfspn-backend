<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Repositories\Contracts\AuthSubjectRepositoryInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class PasswordResetService
{
    public const PURPOSE_RESET = 'reset';

    public const PURPOSE_SETUP = 'setup';

    public function __construct(private readonly TokenService $tokens) {}

    public function issueToken(Authenticatable $subject, int $ttlMinutes, string $purpose = self::PURPOSE_RESET): string
    {
        return Crypt::encryptString(json_encode([
            't' => $subject->getMorphClass(),
            's' => $subject->getKey(),
            'u' => $purpose,
            'exp' => now()->addMinutes($ttlMinutes)->getTimestamp(),
            'f' => $this->fingerprint($subject),
        ]));
    }

    public function reset(string $token, string $password, AuthSubjectRepositoryInterface $subjects, string $purpose = self::PURPOSE_RESET): Authenticatable
    {
        $payload = $this->decode($token, $purpose);

        if (($payload['t'] ?? null) !== (new ($subjects->subjectClass()))->getMorphClass()
            || ($payload['u'] ?? self::PURPOSE_RESET) !== $purpose
            || ($payload['exp'] ?? 0) < now()->getTimestamp()) {
            throw $this->invalid($purpose);
        }

        return DB::transaction(function () use ($payload, $password, $subjects, $purpose) {
            $subject = $subjects->findForUpdate((string) ($payload['s'] ?? ''));

            if (! $subject || ! hash_equals($this->fingerprint($subject), (string) ($payload['f'] ?? ''))) {
                throw $this->invalid($purpose);
            }

            $subjects->resetPassword($subject, $password);
            $this->tokens->revokeAll($subject);

            return $subject;
        });
    }

    private function fingerprint(Authenticatable $subject): string
    {
        return substr(hash('sha256', $subject->getAuthPassword()), 0, 16);
    }

    private function decode(string $token, string $purpose): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            throw $this->invalid($purpose);
        }

        return is_array($payload) ? $payload : throw $this->invalid($purpose);
    }

    private function invalid(string $purpose): ApiException
    {
        return $purpose === self::PURPOSE_SETUP
            ? new ApiException('This link to create your password is invalid or has expired.', 422, ['code' => 'invalid_password_token'])
            : new ApiException('This password reset link is invalid or has expired.', 422, ['code' => 'invalid_reset_token']);
    }
}

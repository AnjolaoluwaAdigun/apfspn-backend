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
    public function __construct(private readonly TokenService $tokens) {}

    public function issueToken(Authenticatable $subject, int $ttlMinutes): string
    {
        return Crypt::encryptString(json_encode([
            't' => $subject->getMorphClass(),
            's' => $subject->getKey(),
            'exp' => now()->addMinutes($ttlMinutes)->getTimestamp(),
            'f' => $this->fingerprint($subject),
        ]));
    }

    public function reset(string $token, string $password, AuthSubjectRepositoryInterface $subjects): Authenticatable
    {
        $payload = $this->decode($token);

        if (($payload['t'] ?? null) !== (new ($subjects->subjectClass()))->getMorphClass() || ($payload['exp'] ?? 0) < now()->getTimestamp()) {
            throw $this->invalid();
        }

        return DB::transaction(function () use ($payload, $password, $subjects) {
            $subject = $subjects->findForUpdate((string) ($payload['s'] ?? ''));

            if (! $subject || ! hash_equals($this->fingerprint($subject), (string) ($payload['f'] ?? ''))) {
                throw $this->invalid();
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

    private function decode(string $token): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            throw $this->invalid();
        }

        return is_array($payload) ? $payload : throw $this->invalid();
    }

    private function invalid(): ApiException
    {
        return new ApiException('This password reset link is invalid or has expired.', 422, ['code' => 'invalid_reset_token']);
    }
}

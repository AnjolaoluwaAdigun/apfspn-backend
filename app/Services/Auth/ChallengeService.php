<?php

namespace App\Services\Auth;

use App\Enums\ChallengeMode;
use App\Enums\ChallengePurpose;
use App\Exceptions\ApiException;
use App\Jobs\SendOtpEmail;
use App\Models\AuthChallenge;
use App\Repositories\Contracts\AuthChallengeRepositoryInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ChallengeService
{
    public function __construct(private readonly AuthChallengeRepositoryInterface $challenges) {}

    public function create(?Model $subject, string $subjectClass, ChallengePurpose $purpose, string $email): array
    {
        $subjectType = (new $subjectClass)->getMorphClass();
        $emailHash = self::emailHash($email);

        return DB::transaction(function () use ($subject, $subjectType, $emailHash, $purpose) {
            $existing = $this->challenges->latestActiveForUpdate($subjectType, $emailHash, $purpose);

            if ($existing) {
                return $this->cycle($existing);
            }

            $code = $this->generateCode();
            $challenge = $this->challenges->create([
                'subject_type' => $subjectType,
                'subject_uuid' => $subject?->getKey(),
                'email_hash' => $emailHash,
                'purpose' => $purpose,
                'code_hash' => $subject ? Hash::make($code) : Hash::make(Str::random(40)),
                'version' => 1,
                'attempts' => 0,
                'send_count' => 1,
                'last_sent_at' => now(),
                'expires_at' => now()->addMinutes(config('apfspn.auth.otp_ttl_minutes')),
            ]);

            $this->send($challenge, $subject, $code);

            return $this->result($challenge, ChallengeMode::SendNew);
        });
    }

    public function reissueWithoutSending(Model $subject, ChallengePurpose $purpose, string $email): array
    {
        return DB::transaction(function () use ($subject, $purpose, $email) {
            $code = $this->generateCode();
            $attributes = [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(config('apfspn.auth.otp_ttl_minutes')),
            ];

            $existing = $this->challenges->latestActiveForUpdate($subject->getMorphClass(), self::emailHash($email), $purpose);

            $challenge = $existing
                ? $this->challenges->update($existing, $attributes + ['version' => $existing->version + 1])
                : $this->challenges->create($attributes + [
                    'subject_type' => $subject->getMorphClass(),
                    'subject_uuid' => $subject->getKey(),
                    'email_hash' => self::emailHash($email),
                    'purpose' => $purpose,
                    'version' => 1,
                    'send_count' => 0,
                    'last_sent_at' => now(),
                ]);

            return ['code' => $code, 'challenge' => $this->result($challenge, ChallengeMode::SendNew)];
        });
    }

    public function resend(string $token, string $subjectClass): array
    {
        $payload = $this->decode($token);

        return DB::transaction(function () use ($payload, $subjectClass) {
            $challenge = $this->lockActive($payload, $subjectClass);

            return $this->cycle($challenge);
        });
    }

    public function verify(string $token, string $code, array $purposes, string $subjectClass): AuthChallenge
    {
        $payload = $this->decode($token);

        if (! in_array(ChallengePurpose::tryFrom($payload['p'] ?? ''), $purposes, true)) {
            throw $this->invalidToken();
        }

        $outcome = DB::transaction(function () use ($payload, $code, $subjectClass) {
            $challenge = $this->lockActive($payload, $subjectClass);
            $maxAttempts = config('apfspn.auth.max_attempts');

            if ($challenge->attempts >= $maxAttempts) {
                return ['blocked', $challenge];
            }

            if (! $challenge->subject_uuid || ! Hash::check($code, $challenge->code_hash)) {
                $this->challenges->update($challenge, ['attempts' => $challenge->attempts + 1]);

                return ['invalid', $challenge];
            }

            $this->challenges->update($challenge, ['consumed_at' => now()]);

            return ['ok', $challenge];
        });

        [$status, $challenge] = $outcome;

        return match ($status) {
            'ok' => $challenge->load('subject'),
            'blocked' => throw $this->blocked($challenge),
            'invalid' => throw new ApiException('The code you entered is incorrect.', 422, [
                'code' => 'invalid_otp',
                'attempts_remaining' => max(0, config('apfspn.auth.max_attempts') - $challenge->attempts),
            ]),
        };
    }

    public function prune(bool $dryRun): int
    {
        $before = now()->subDay();

        return $dryRun ? $this->challenges->countPrunable($before) : $this->challenges->prune($before);
    }

    public static function emailHash(string $email): string
    {
        return hash('sha256', Str::lower(trim($email)));
    }

    private function cycle(AuthChallenge $challenge): array
    {
        $cooldown = config('apfspn.auth.resend_cooldown_seconds');
        $elapsed = (int) $challenge->last_sent_at->diffInSeconds(now(), true);

        if ($challenge->send_count >= config('apfspn.auth.max_sends')) {
            throw $this->blocked($challenge);
        }

        if ($elapsed < $cooldown) {
            return $this->result($challenge, ChallengeMode::Reuse, $cooldown - $elapsed);
        }

        $code = $this->generateCode();
        $this->challenges->update($challenge, [
            'code_hash' => $challenge->subject_uuid ? Hash::make($code) : Hash::make(Str::random(40)),
            'version' => $challenge->version + 1,
            'send_count' => $challenge->send_count + 1,
            'attempts' => 0,
            'last_sent_at' => now(),
            'expires_at' => now()->addMinutes(config('apfspn.auth.otp_ttl_minutes')),
        ]);

        $this->send($challenge, $challenge->subject_uuid ? $challenge->subject : null, $code);

        return $this->result($challenge, ChallengeMode::SendNew);
    }

    private function lockActive(array $payload, string $subjectClass): AuthChallenge
    {
        $challenge = $this->challenges->findForUpdate((string) ($payload['c'] ?? ''));

        if (! $challenge || $challenge->subject_type !== (new $subjectClass)->getMorphClass()) {
            throw $this->invalidToken();
        }

        if ($challenge->consumed_at || $challenge->version !== (int) ($payload['v'] ?? 0)) {
            throw new ApiException('This code has been replaced by a newer one. Use the latest code sent to you.', 409, ['code' => 'stale_challenge_token']);
        }

        if ($challenge->expires_at->isPast()) {
            throw new ApiException('This code has expired. Please request a new one.', 422, ['code' => 'otp_expired']);
        }

        return $challenge;
    }

    private function send(AuthChallenge $challenge, ?Model $subject, string $code): void
    {
        if ($subject) {
            SendOtpEmail::dispatch($subject->email, $code, $challenge->purpose, $challenge->getKey(), $challenge->version)->afterCommit();
        }
    }

    private function result(AuthChallenge $challenge, ChallengeMode $mode, ?int $retryAfter = null): array
    {
        return [
            'challenge_token' => Crypt::encryptString(json_encode([
                'c' => $challenge->getKey(),
                'v' => $challenge->version,
                'p' => $challenge->purpose->value,
            ])),
            'mode' => $mode,
            'expires_in' => (int) now()->diffInSeconds($challenge->expires_at, true),
            'retry_after_seconds' => $retryAfter ?? config('apfspn.auth.resend_cooldown_seconds'),
        ];
    }

    private function decode(string $token): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            throw $this->invalidToken();
        }

        return is_array($payload) ? $payload : throw $this->invalidToken();
    }

    private function generateCode(): string
    {
        $length = config('apfspn.auth.otp_length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    private function invalidToken(): ApiException
    {
        return new ApiException('This verification session is invalid. Please start again.', 422, ['code' => 'invalid_challenge_token']);
    }

    private function blocked(AuthChallenge $challenge): ApiException
    {
        return new ApiException('Too many attempts. Please try again later.', 429, [
            'code' => 'otp_blocked',
            'retry_after_seconds' => max(1, (int) now()->diffInSeconds($challenge->expires_at, true)),
        ]);
    }
}

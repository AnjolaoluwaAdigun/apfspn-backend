<?php

namespace Tests\Feature\Auth;

use App\Enums\ChallengeMode;
use App\Enums\ChallengePurpose;
use App\Exceptions\ApiException;
use App\Jobs\SendOtpEmail;
use App\Models\Admin;
use App\Models\AuthChallenge;
use App\Models\User;
use App\Services\Auth\ChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChallengeServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChallengeService $challenges;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->challenges = app(ChallengeService::class);
    }

    public function test_only_a_bcrypt_hash_of_the_code_is_stored(): void
    {
        $user = User::factory()->create();
        $this->challenges->create($user, User::class, ChallengePurpose::PasswordReset, $user->email);

        $code = Queue::pushed(SendOtpEmail::class)->last()->code;
        $row = AuthChallenge::first();

        $this->assertNotSame($code, $row->code_hash);
        $this->assertStringStartsWith('$2y$', $row->code_hash);
    }

    public function test_repeated_create_within_cooldown_reuses_the_challenge(): void
    {
        $user = User::factory()->create();

        $first = $this->challenges->create($user, User::class, ChallengePurpose::PasswordReset, $user->email);
        $second = $this->challenges->create($user, User::class, ChallengePurpose::PasswordReset, $user->email);

        $this->assertSame(ChallengeMode::SendNew, $first['mode']);
        $this->assertSame(ChallengeMode::Reuse, $second['mode']);
        Queue::assertPushed(SendOtpEmail::class, 1);
    }

    public function test_sends_are_blocked_after_the_limit(): void
    {
        $user = User::factory()->create();
        $result = $this->challenges->create($user, User::class, ChallengePurpose::PasswordReset, $user->email);

        foreach (range(2, config('apfspn.auth.max_sends')) as $send) {
            $this->travel(61)->seconds();
            $result = $this->challenges->resend($result['challenge_token'], User::class);
        }

        $this->travel(61)->seconds();

        try {
            $this->challenges->resend($result['challenge_token'], User::class);
            $this->fail('Expected the resend to be blocked.');
        } catch (ApiException $e) {
            $this->assertSame(429, $e->status());
            $this->assertSame('otp_blocked', $e->payload()['code']);
            $this->assertArrayHasKey('retry_after_seconds', $e->payload());
        }
    }

    public function test_decoy_challenge_for_unknown_email_never_verifies_and_sends_nothing(): void
    {
        $result = $this->challenges->create(null, User::class, ChallengePurpose::PasswordReset, 'ghost@example.com');

        Queue::assertNotPushed(SendOtpEmail::class);

        foreach (['000000', '123456'] as $code) {
            try {
                $this->challenges->verify($result['challenge_token'], $code, [ChallengePurpose::PasswordReset], User::class);
                $this->fail('Decoy challenge must never verify.');
            } catch (ApiException $e) {
                $this->assertSame('invalid_otp', $e->payload()['code']);
            }
        }
    }

    public function test_challenge_token_from_another_audience_is_invalid(): void
    {
        $user = User::factory()->create();
        $result = $this->challenges->create($user, User::class, ChallengePurpose::PasswordReset, $user->email);

        try {
            $this->challenges->verify($result['challenge_token'], '000000', [ChallengePurpose::PasswordReset], Admin::class);
            $this->fail('Expected audience mismatch to be rejected.');
        } catch (ApiException $e) {
            $this->assertSame('invalid_challenge_token', $e->payload()['code']);
        }
    }

    public function test_tampered_challenge_token_is_invalid(): void
    {
        try {
            $this->challenges->verify('not-a-token', '000000', [ChallengePurpose::PasswordReset], User::class);
            $this->fail('Expected tampered token to be rejected.');
        } catch (ApiException $e) {
            $this->assertSame('invalid_challenge_token', $e->payload()['code']);
        }
    }

    public function test_otp_job_is_unique_per_challenge_version(): void
    {
        $user = User::factory()->create();
        $this->challenges->create($user, User::class, ChallengePurpose::MemberLogin, $user->email);

        $job = Queue::pushed(SendOtpEmail::class)->last();

        $this->assertSame($job->challengeUuid.':1', $job->uniqueId());
    }
}

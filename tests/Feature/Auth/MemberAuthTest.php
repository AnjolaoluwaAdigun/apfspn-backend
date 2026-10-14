<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendOtpEmail;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MemberAuthTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_login_with_two_factor_returns_challenge_then_tokens(): void
    {
        $user = User::factory()->create();

        $challenge = $this->api('POST', '/api/v1/member/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'verify_otp')
            ->assertJsonPath('data.challenge.mode', 'send_new')
            ->assertJsonMissingPath('data.tokens')
            ->json('data.challenge.challenge_token');

        Queue::assertPushed(SendOtpEmail::class, fn (SendOtpEmail $job) => $job->email === $user->email);

        $this->api('POST', '/api/v1/member/auth/verify-login-otp', ['challenge_token' => $challenge, 'code' => $this->lastOtp(), 'client' => 'mobile'])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'authenticated')
            ->assertJsonPath('data.member.uuid', $user->uuid)
            ->assertJsonStructure(['data' => ['tokens' => ['access_token', 'refresh_token']]]);

        $this->assertStringStartsWith('mobile:', $user->tokens()->first()->name);
    }

    public function test_login_without_two_factor_returns_tokens(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();

        $this->api('POST', '/api/v1/member/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'authenticated')
            ->assertJsonStructure(['data' => ['tokens' => ['access_token'], 'member' => ['uuid', 'member_type']]]);

        Queue::assertNotPushed(SendOtpEmail::class);
    }

    public function test_wrong_password_is_rejected_opaquely(): void
    {
        $user = User::factory()->create();

        $this->api('POST', '/api/v1/member/auth/login', ['email' => $user->email, 'password' => 'WrongPass1'])
            ->assertUnauthorized()
            ->assertJsonPath('data.code', 'invalid_credentials');

        $this->api('POST', '/api/v1/member/auth/login', ['email' => 'ghost@example.com', 'password' => 'WrongPass1'])
            ->assertUnauthorized()
            ->assertJsonPath('data.code', 'invalid_credentials');
    }

    public function test_expired_login_otp_is_rejected(): void
    {
        $user = User::factory()->create();
        $challenge = $this->api('POST', '/api/v1/member/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])->json('data.challenge.challenge_token');
        $code = $this->lastOtp();

        $this->travel(11)->minutes();

        $this->api('POST', '/api/v1/member/auth/verify-login-otp', ['challenge_token' => $challenge, 'code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'otp_expired');
    }

    public function test_resend_within_cooldown_reuses_and_after_cooldown_makes_old_token_stale(): void
    {
        $user = User::factory()->create();
        $first = $this->api('POST', '/api/v1/member/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])->json('data.challenge.challenge_token');
        $firstCode = $this->lastOtp();

        $this->api('POST', '/api/v1/member/auth/resend-otp', ['challenge_token' => $first])
            ->assertOk()
            ->assertJsonPath('data.mode', 'reuse');
        Queue::assertPushed(SendOtpEmail::class, 1);

        $this->travel(61)->seconds();

        $second = $this->api('POST', '/api/v1/member/auth/resend-otp', ['challenge_token' => $first])
            ->assertOk()
            ->assertJsonPath('data.mode', 'send_new')
            ->json('data.challenge_token');
        Queue::assertPushed(SendOtpEmail::class, 2);

        $this->api('POST', '/api/v1/member/auth/verify-login-otp', ['challenge_token' => $first, 'code' => $firstCode])
            ->assertStatus(409)
            ->assertJsonPath('data.code', 'stale_challenge_token');

        $this->api('POST', '/api/v1/member/auth/verify-login-otp', ['challenge_token' => $second, 'code' => $this->lastOtp()])->assertOk();
    }

    public function test_wrong_codes_count_down_then_block(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create();
        $challenge = $this->api('POST', '/api/v1/member/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])->json('data.challenge.challenge_token');
        $wrong = $this->lastOtp() === '000000' ? '111111' : '000000';

        $this->api('POST', '/api/v1/member/auth/verify-login-otp', ['challenge_token' => $challenge, 'code' => $wrong])
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_otp')
            ->assertJsonPath('data.attempts_remaining', 4);

        foreach (range(1, 4) as $attempt) {
            $this->api('POST', '/api/v1/member/auth/verify-login-otp', ['challenge_token' => $challenge, 'code' => $wrong]);
        }

        $this->api('POST', '/api/v1/member/auth/verify-login-otp', ['challenge_token' => $challenge, 'code' => $this->lastOtp()])
            ->assertStatus(429)
            ->assertJsonPath('data.code', 'otp_blocked');
    }

    public function test_forgot_password_reset_flow_and_reset_token_is_single_use(): void
    {
        $user = User::factory()->create();

        $challenge = $this->api('POST', '/api/v1/member/auth/forgot-password', ['email' => $user->email])->assertOk()->json('data.challenge_token');

        $resetToken = $this->api('POST', '/api/v1/member/auth/verify-otp', ['challenge_token' => $challenge, 'code' => $this->lastOtp()])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'reset_password')
            ->json('data.reset_token');

        $payload = ['reset_token' => $resetToken, 'password' => 'NewPassword1', 'password_confirmation' => 'NewPassword1'];

        $this->api('POST', '/api/v1/member/auth/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('NewPassword1', $user->fresh()->password));

        $this->api('POST', '/api/v1/member/auth/reset-password', $payload)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_reset_token');
    }

    public function test_change_password_keeps_current_session_and_revokes_others(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $current = $this->memberTokens($user);
        $other = $this->memberTokens($user);

        $this->api('POST', '/api/v1/member/auth/change-password', [
            'current_password' => UserFactory::PASSWORD,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ], $current['access_token'])->assertOk();

        $this->api('GET', '/api/v1/member/auth/me', [], $current['access_token'])->assertOk();
        $this->api('GET', '/api/v1/member/auth/me', [], $other['access_token'])->assertUnauthorized();
        $this->assertTrue(Hash::check('NewPassword1', $user->fresh()->password));
    }

    public function test_change_password_with_wrong_current_password_fails(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $tokens = $this->memberTokens($user);

        $this->api('POST', '/api/v1/member/auth/change-password', [
            'current_password' => 'NotMyPassword1',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ], $tokens['access_token'])
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_current_password');
    }

    public function test_weak_new_password_fails_validation(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $tokens = $this->memberTokens($user);

        $this->api('POST', '/api/v1/member/auth/change-password', [
            'current_password' => UserFactory::PASSWORD,
            'password' => 'short',
            'password_confirmation' => 'short',
        ], $tokens['access_token'])
            ->assertStatus(422)
            ->assertJsonStructure(['data' => ['password']]);
    }

    public function test_refresh_logout_and_me(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $tokens = $this->memberTokens($user);

        $this->api('GET', '/api/v1/member/auth/me', [], $tokens['access_token'])
            ->assertOk()
            ->assertJsonPath('data.uuid', $user->uuid)
            ->assertJsonPath('data.member_type', 'individual');

        $fresh = $this->api('POST', '/api/v1/member/auth/refresh', [], $tokens['refresh_token'])->assertOk()->json('data');
        $this->api('GET', '/api/v1/member/auth/me', [], $tokens['access_token'])->assertUnauthorized();

        $this->api('POST', '/api/v1/member/auth/logout', [], $fresh['access_token'])->assertOk();
        $this->api('GET', '/api/v1/member/auth/me', [], $fresh['access_token'])->assertUnauthorized();
        $this->api('POST', '/api/v1/member/auth/refresh', [], $fresh['refresh_token'])->assertUnauthorized();
    }

    public function test_login_validation_error_uses_envelope(): void
    {
        $this->api('POST', '/api/v1/member/auth/login', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('error', true)
            ->assertJsonStructure(['data' => ['email', 'password']]);
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Enums\ePermission;
use App\Jobs\SendOtpEmail;
use App\Models\Admin;
use Database\Factories\AdminFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_login_returns_tokens_and_admin_profile(): void
    {
        $admin = $this->superAdmin();

        $this->api('POST', '/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => AdminFactory::PASSWORD])
            ->assertOk()
            ->assertJsonPath('error', false)
            ->assertJsonPath('data.next_step', 'authenticated')
            ->assertJsonPath('data.admin.uuid', $admin->uuid)
            ->assertJsonPath('data.admin.roles', ['super_admin'])
            ->assertJsonStructure(['data' => ['tokens' => ['access_token', 'refresh_token', 'token_type', 'expires_in', 'refresh_expires_in']]]);

        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->assertEqualsCanonicalizing(
            ['web', 'web:refresh'],
            $admin->tokens()->pluck('name')->map(fn ($name) => preg_replace('/^web:[a-z0-9]{20}/', 'web', $name))->all(),
        );
    }

    public function test_login_with_admin_two_factor_requires_otp(): void
    {
        config(['apfspn.auth.admin_two_factor' => true]);
        $admin = $this->superAdmin();

        $challenge = $this->api('POST', '/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => AdminFactory::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'verify_otp')
            ->assertJsonMissingPath('data.tokens')
            ->json('data.challenge.challenge_token');

        Queue::assertPushed(SendOtpEmail::class, fn (SendOtpEmail $job) => $job->email === $admin->email);

        $this->api('POST', '/api/v1/admin/auth/verify-otp', ['challenge_token' => $challenge, 'code' => $this->lastOtp()])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'authenticated')
            ->assertJsonStructure(['data' => ['tokens' => ['access_token', 'refresh_token']]]);
    }

    public function test_wrong_password_and_unknown_email_get_the_same_response(): void
    {
        $admin = $this->superAdmin();
        $expected = ['error' => true, 'message' => 'These credentials do not match our records.', 'data' => ['code' => 'invalid_credentials']];

        $this->api('POST', '/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => 'WrongPass1'])
            ->assertUnauthorized()
            ->assertExactJson($expected);

        $this->api('POST', '/api/v1/admin/auth/login', ['email' => 'nobody@apfspn.test', 'password' => 'WrongPass1'])
            ->assertUnauthorized()
            ->assertExactJson($expected);
    }

    public function test_must_reset_password_blocks_login(): void
    {
        $admin = $this->superAdmin(['must_reset_password' => true]);

        $this->api('POST', '/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => AdminFactory::PASSWORD])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'must_reset_password');
    }

    public function test_must_reset_password_blocks_refresh(): void
    {
        $admin = $this->superAdmin();
        $tokens = $this->adminTokens($admin);
        $admin->update(['must_reset_password' => true]);

        $this->api('POST', '/api/v1/admin/auth/refresh', [], $tokens['refresh_token'])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'must_reset_password');
    }

    public function test_forgot_password_verify_otp_and_reset_flow(): void
    {
        $admin = $this->superAdmin(['must_reset_password' => true]);

        $challenge = $this->api('POST', '/api/v1/admin/auth/forgot-password', ['email' => $admin->email])
            ->assertOk()
            ->assertJsonPath('data.mode', 'send_new')
            ->json('data.challenge_token');

        $resetToken = $this->api('POST', '/api/v1/admin/auth/verify-otp', ['challenge_token' => $challenge, 'code' => $this->lastOtp()])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'reset_password')
            ->json('data.reset_token');

        $this->api('POST', '/api/v1/admin/auth/reset-password', [
            'reset_token' => $resetToken,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertOk()->assertJsonPath('error', false);

        $admin->refresh();
        $this->assertFalse($admin->must_reset_password);
        $this->assertTrue(Hash::check('NewPassword1', $admin->password));

        $this->api('POST', '/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => 'NewPassword1'])->assertOk();
    }

    public function test_reset_revokes_existing_sessions(): void
    {
        $admin = $this->superAdmin();
        $tokens = $this->adminTokens($admin);

        $challenge = $this->api('POST', '/api/v1/admin/auth/forgot-password', ['email' => $admin->email])->json('data.challenge_token');
        $resetToken = $this->api('POST', '/api/v1/admin/auth/verify-otp', ['challenge_token' => $challenge, 'code' => $this->lastOtp()])->json('data.reset_token');
        $this->api('POST', '/api/v1/admin/auth/reset-password', ['reset_token' => $resetToken, 'password' => 'NewPassword1', 'password_confirmation' => 'NewPassword1'])->assertOk();

        $this->api('GET', '/api/v1/admin/auth/me', [], $tokens['access_token'])->assertUnauthorized();
    }

    public function test_forgot_password_for_unknown_email_looks_the_same_and_sends_nothing(): void
    {
        $this->api('POST', '/api/v1/admin/auth/forgot-password', ['email' => 'nobody@apfspn.test'])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for this email, a reset code has been sent.')
            ->assertJsonPath('data.mode', 'send_new')
            ->assertJsonStructure(['data' => ['challenge_token', 'mode', 'expires_in', 'retry_after_seconds']]);

        Queue::assertNotPushed(SendOtpEmail::class);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $admin = $this->superAdmin();
        $challenge = $this->api('POST', '/api/v1/admin/auth/forgot-password', ['email' => $admin->email])->json('data.challenge_token');
        $code = $this->lastOtp();

        $this->travel(11)->minutes();

        $this->api('POST', '/api/v1/admin/auth/verify-otp', ['challenge_token' => $challenge, 'code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'otp_expired');
    }

    public function test_refresh_rotates_tokens_and_invalidates_the_old_refresh_token(): void
    {
        $admin = $this->superAdmin();
        $tokens = $this->adminTokens($admin);

        $fresh = $this->api('POST', '/api/v1/admin/auth/refresh', [], $tokens['refresh_token'])
            ->assertOk()
            ->json('data');

        $this->assertNotSame($tokens['access_token'], $fresh['access_token']);
        $this->api('GET', '/api/v1/admin/auth/me', [], $fresh['access_token'])->assertOk();
        $this->api('GET', '/api/v1/admin/auth/me', [], $tokens['access_token'])->assertUnauthorized();
        $this->api('POST', '/api/v1/admin/auth/refresh', [], $tokens['refresh_token'])->assertUnauthorized();
        $this->assertSame(2, $admin->tokens()->count());
    }

    public function test_logout_revokes_the_session(): void
    {
        $admin = $this->superAdmin();
        $tokens = $this->adminTokens($admin);

        $this->api('POST', '/api/v1/admin/auth/logout', [], $tokens['access_token'])->assertOk();

        $this->api('GET', '/api/v1/admin/auth/me', [], $tokens['access_token'])->assertUnauthorized();
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_me_returns_roles_and_permissions(): void
    {
        $admin = $this->superAdmin();
        $tokens = $this->adminTokens($admin);

        $response = $this->api('GET', '/api/v1/admin/auth/me', [], $tokens['access_token'])
            ->assertOk()
            ->assertJsonPath('data.email', $admin->email)
            ->assertJsonPath('data.roles', ['super_admin']);

        $this->assertEqualsCanonicalizing(ePermission::values(), $response->json('data.permissions'));
    }

    public function test_disabled_admin_cannot_log_in(): void
    {
        $admin = Admin::factory()->disabled()->create();

        $this->api('POST', '/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => AdminFactory::PASSWORD])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'account_disabled');
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\AdminFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthEndpointFailuresTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_admin_can_resend_a_login_code(): void
    {
        config(['apfspn.auth.admin_two_factor' => true]);
        $admin = $this->superAdmin();
        $challenge = $this->api('POST', '/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => AdminFactory::PASSWORD])
            ->assertOk()
            ->json('data.challenge.challenge_token');

        $this->travel(61)->seconds();

        $this->api('POST', '/api/v1/admin/auth/resend-otp', ['challenge_token' => $challenge])
            ->assertOk()
            ->assertJsonPath('error', false)
            ->assertJsonPath('data.mode', 'send_new');
    }

    public static function malformedRequests(): array
    {
        return [
            'admin resend otp without a token' => ['/api/v1/admin/auth/resend-otp', [], 'challenge_token'],
            'member resend otp without a token' => ['/api/v1/member/auth/resend-otp', [], 'challenge_token'],
            'admin forgot password with a bad email' => ['/api/v1/admin/auth/forgot-password', ['email' => 'not-an-email'], 'email'],
            'member forgot password with a bad email' => ['/api/v1/member/auth/forgot-password', ['email' => 'not-an-email'], 'email'],
            'member resend verification with a bad email' => ['/api/v1/member/register/resend-verification', ['email' => 'not-an-email'], 'email'],
        ];
    }

    #[DataProvider('malformedRequests')]
    public function test_malformed_requests_are_rejected(string $url, array $payload, string $field): void
    {
        $this->api('POST', $url, $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error', true)
            ->assertJsonStructure(['data' => [$field]]);
    }

    public function test_member_reset_code_must_match(): void
    {
        $member = User::factory()->create();
        $challenge = $this->api('POST', '/api/v1/member/auth/forgot-password', ['email' => $member->email])
            ->assertOk()
            ->json('data.challenge_token');

        $this->api('POST', '/api/v1/member/auth/verify-otp', ['challenge_token' => $challenge, 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonPath('error', true);
    }

    public function test_logout_needs_a_token(): void
    {
        $this->api('POST', '/api/v1/admin/auth/logout')->assertUnauthorized()->assertJsonPath('error', true);
        $this->api('POST', '/api/v1/member/auth/logout')->assertUnauthorized()->assertJsonPath('error', true);
    }
}

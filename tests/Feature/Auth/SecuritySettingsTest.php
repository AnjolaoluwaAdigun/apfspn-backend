<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_two_factor_is_on_by_default(): void
    {
        $user = User::factory()->create();

        $this->api('GET', '/api/v1/member/security', [], $this->memberTokens($user)['access_token'])
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', true);
    }

    public function test_member_can_turn_two_factor_off_with_password_and_back_on(): void
    {
        $user = User::factory()->create();
        $token = $this->memberTokens($user)['access_token'];

        $this->api('PUT', '/api/v1/member/security', ['two_factor_enabled' => false, 'current_password' => UserFactory::PASSWORD], $token)
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', false);

        $this->api('POST', '/api/v1/member/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'authenticated');

        $this->api('PUT', '/api/v1/member/security', ['two_factor_enabled' => true], $token)
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', true);

        $this->assertTrue($user->refresh()->two_factor_enabled);
    }

    public function test_turning_two_factor_off_requires_correct_password(): void
    {
        $user = User::factory()->create();
        $token = $this->memberTokens($user)['access_token'];

        $this->api('PUT', '/api/v1/member/security', ['two_factor_enabled' => false], $token)
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password', 'data');

        $this->api('PUT', '/api/v1/member/security', ['two_factor_enabled' => false, 'current_password' => 'WrongPassword1'], $token)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_current_password');

        $this->assertTrue($user->refresh()->two_factor_enabled);
    }

    public function test_admin_token_cannot_use_member_security(): void
    {
        $admin = $this->superAdmin();

        $this->api('GET', '/api/v1/member/security', [], $this->adminTokens($admin)['access_token'])
            ->assertStatus(403)
            ->assertJsonPath('data.code', 'wrong_audience');
    }
}

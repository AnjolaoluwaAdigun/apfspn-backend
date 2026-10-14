<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TokenGuardTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_refresh_token_cannot_be_used_as_bearer(): void
    {
        $tokens = $this->memberTokens(User::factory()->withoutTwoFactor()->create());

        $this->api('GET', '/api/v1/member/auth/me', [], $tokens['refresh_token'])
            ->assertUnauthorized()
            ->assertJsonPath('data.code', 'refresh_token_not_allowed');

        $adminTokens = $this->adminTokens($this->superAdmin());

        $this->api('GET', '/api/v1/admin/auth/me', [], $adminTokens['refresh_token'])
            ->assertUnauthorized()
            ->assertJsonPath('data.code', 'refresh_token_not_allowed');
    }

    public function test_access_token_cannot_refresh(): void
    {
        $tokens = $this->memberTokens(User::factory()->withoutTwoFactor()->create());

        $this->api('POST', '/api/v1/member/auth/refresh', [], $tokens['access_token'])
            ->assertUnauthorized()
            ->assertJsonPath('data.code', 'refresh_token_required');
    }

    public function test_member_token_is_rejected_on_admin_routes(): void
    {
        $tokens = $this->memberTokens(User::factory()->withoutTwoFactor()->create());

        $this->api('GET', '/api/v1/admin/auth/me', [], $tokens['access_token'])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'wrong_audience');

        $this->api('POST', '/api/v1/admin/auth/refresh', [], $tokens['refresh_token'])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'wrong_audience');
    }

    public function test_admin_token_is_rejected_on_member_routes(): void
    {
        $tokens = $this->adminTokens($this->superAdmin());

        $this->api('GET', '/api/v1/member/auth/me', [], $tokens['access_token'])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'wrong_audience');
    }

    public function test_disabled_member_token_stops_working(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $tokens = $this->memberTokens($user);
        $user->update(['status' => UserStatus::Disabled]);

        $this->api('GET', '/api/v1/member/auth/me', [], $tokens['access_token'])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'account_disabled');
    }

    public function test_missing_token_is_unauthenticated(): void
    {
        $this->api('GET', '/api/v1/member/auth/me')
            ->assertUnauthorized()
            ->assertExactJson(['error' => true, 'message' => 'Unauthenticated.', 'data' => null]);
    }
}

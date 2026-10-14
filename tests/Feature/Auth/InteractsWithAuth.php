<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendOtpEmail;
use App\Models\Admin;
use App\Models\User;
use Database\Factories\AdminFactory;
use Database\Factories\UserFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

trait InteractsWithAuth
{
    protected function api(string $method, string $uri, array $data = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        $headers = $token ? ['Authorization' => 'Bearer '.$token] : [];

        return $this->json($method, $uri, $data, $headers);
    }

    protected function lastOtp(): string
    {
        return Queue::pushed(SendOtpEmail::class)->last()->code;
    }

    protected function superAdmin(array $attributes = []): Admin
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return tap(Admin::factory()->create($attributes))->assignRole('super_admin');
    }

    protected function adminTokens(Admin $admin): array
    {
        return $this->api('POST', '/api/v1/admin/auth/login', [
            'email' => $admin->email,
            'password' => AdminFactory::PASSWORD,
        ])->assertOk()->json('data.tokens');
    }

    protected function memberTokens(User $user): array
    {
        $response = $this->api('POST', '/api/v1/member/auth/login', [
            'email' => $user->email,
            'password' => UserFactory::PASSWORD,
        ])->assertOk();

        if ($response->json('data.next_step') === 'authenticated') {
            return $response->json('data.tokens');
        }

        return $this->api('POST', '/api/v1/member/auth/verify-login-otp', [
            'challenge_token' => $response->json('data.challenge.challenge_token'),
            'code' => $this->lastOtp(),
        ])->assertOk()->json('data.tokens');
    }
}

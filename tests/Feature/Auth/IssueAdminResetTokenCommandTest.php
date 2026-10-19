<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IssueAdminResetTokenCommandTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    public function test_prints_only_a_token_that_resets_the_admin_password_locally(): void
    {
        $this->app['env'] = 'local';
        $admin = Admin::factory()->mustResetPassword()->create();

        $this->assertSame(0, Artisan::call('apfspn:admin-reset-token', ['email' => $admin->email]));
        $token = trim(Artisan::output());

        $this->assertNotSame('', $token);
        $this->assertStringNotContainsString("\n", $token);

        $this->api('POST', '/api/v1/admin/auth/reset-password', [
            'reset_token' => $token,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertOk();

        $admin->refresh();
        $this->assertFalse($admin->must_reset_password);
        $this->assertTrue(Hash::check('NewPassword1', $admin->password));
    }

    public function test_refuses_outside_local(): void
    {
        $admin = Admin::factory()->create();

        foreach (['testing', 'production', 'staging'] as $env) {
            $this->app['env'] = $env;

            $this->artisan('apfspn:admin-reset-token', ['email' => $admin->email])
                ->expectsOutput('This command only runs when APP_ENV=local.')
                ->doesntExpectOutputToContain('eyJ')
                ->assertFailed();
        }
    }

    public function test_fails_for_unknown_email(): void
    {
        $this->app['env'] = 'local';

        $this->artisan('apfspn:admin-reset-token', ['email' => 'nobody@apfspn.test'])
            ->expectsOutput('No admin found with that email.')
            ->assertFailed();
    }
}

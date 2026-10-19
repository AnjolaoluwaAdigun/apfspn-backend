<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Services\Auth\AdminAuthService;
use Database\Factories\AdminFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminAuthAuditTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_successful_login_is_audited_with_the_admin_as_actor(): void
    {
        $admin = $this->superAdmin();

        $this->api('POST', '/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => AdminFactory::PASSWORD])->assertOk();

        $log = AuditLog::sole();

        $this->assertSame('admin.login', $log->action);
        $this->assertSame($admin->uuid, $log->admin_uuid);
        $this->assertSame($admin->uuid, $log->auditable_uuid);
        $this->assertSame('[REDACTED]', $log->request_body['password']);
        $this->assertStringStartsWith('sha256:', $log->request_body['email']);
    }

    public function test_failed_logins_are_not_audited(): void
    {
        $admin = $this->superAdmin();

        $this->api('POST', '/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => 'WrongPass1'])->assertUnauthorized();
        $this->api('POST', '/api/v1/admin/auth/login', ['email' => 'nobody@apfspn.test', 'password' => 'WrongPass1'])->assertUnauthorized();
        $this->api('POST', '/api/v1/admin/auth/login', ['email' => Admin::factory()->disabled()->create()->email, 'password' => AdminFactory::PASSWORD])->assertForbidden();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_two_factor_login_is_audited_only_once_the_code_is_verified(): void
    {
        config(['apfspn.auth.admin_two_factor' => true]);
        $admin = $this->superAdmin();

        $challenge = $this->api('POST', '/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => AdminFactory::PASSWORD])
            ->assertOk()
            ->json('data.challenge.challenge_token');

        $this->assertSame(0, AuditLog::count());

        $this->api('POST', '/api/v1/admin/auth/verify-otp', ['challenge_token' => $challenge, 'code' => '000000'])->assertUnprocessable();
        $this->assertSame(0, AuditLog::count());

        $this->api('POST', '/api/v1/admin/auth/verify-otp', ['challenge_token' => $challenge, 'code' => $this->lastOtp()])->assertOk();

        $this->assertSame(['admin.login'], AuditLog::pluck('action')->all());
    }

    public function test_logout_is_audited(): void
    {
        $admin = $this->superAdmin();
        $token = $this->adminTokens($admin)['access_token'];

        $this->api('POST', '/api/v1/admin/auth/logout', [], $token)->assertOk();

        $log = AuditLog::where('action', 'admin.logout')->sole();
        $this->assertSame($admin->uuid, $log->admin_uuid);
        $this->assertSame($admin->uuid, $log->auditable_uuid);
    }

    public function test_password_reset_is_audited_without_the_password(): void
    {
        $admin = $this->superAdmin(['must_reset_password' => false]);
        $resetToken = app(AdminAuthService::class)->issueResetToken($admin->email);

        $this->api('POST', '/api/v1/admin/auth/reset-password', [
            'reset_token' => $resetToken,
            'password' => 'BrandNewPass1',
            'password_confirmation' => 'BrandNewPass1',
        ])->assertOk();

        $log = AuditLog::where('action', 'admin.password_reset')->sole();

        $this->assertSame($admin->uuid, $log->admin_uuid);
        $this->assertSame(['password'], $log->changes['changed_fields']);
        $this->assertSame('[REDACTED]', $log->changes['previous']['password']);
        $this->assertSame('[REDACTED]', $log->request_body['password']);
        $this->assertSame('[REDACTED]', $log->request_body['reset_token']);
    }

    public function test_accepting_an_invite_records_the_cleared_reset_flag(): void
    {
        $admin = Admin::factory()->mustResetPassword()->create();
        $resetToken = app(AdminAuthService::class)->issueResetToken($admin->email);

        $this->api('POST', '/api/v1/admin/auth/reset-password', [
            'reset_token' => $resetToken,
            'password' => 'BrandNewPass1',
            'password_confirmation' => 'BrandNewPass1',
        ])->assertOk();

        $log = AuditLog::where('action', 'admin.password_reset')->sole();

        $this->assertEqualsCanonicalizing(['password', 'must_reset_password'], $log->changes['changed_fields']);
        $this->assertTrue((bool) $log->changes['previous']['must_reset_password']);
    }

    public function test_a_refused_reset_is_not_audited(): void
    {
        $this->api('POST', '/api/v1/admin/auth/reset-password', [
            'reset_token' => 'not-a-valid-token',
            'password' => 'BrandNewPass1',
            'password_confirmation' => 'BrandNewPass1',
        ])->assertUnprocessable();

        $this->assertSame(0, AuditLog::count());
    }
}

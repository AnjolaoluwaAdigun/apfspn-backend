<?php

namespace Tests\Feature\Auth;

use App\Mail\AdminInviteMail;
use App\Models\Admin;
use App\Services\Auth\AdminInviteService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminInviteTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_invite_creates_admin_requiring_reset_and_sends_email_synchronously(): void
    {
        $admin = app(AdminInviteService::class)->invite('Ada Admin', 'Ada@Example.com', ['editor']);

        $this->assertSame('ada@example.com', $admin->email);
        $this->assertTrue($admin->must_reset_password);
        $this->assertTrue($admin->hasRole('editor'));

        Mail::assertSent(AdminInviteMail::class, fn (AdminInviteMail $mail) => $mail->hasTo('ada@example.com')
            && str_starts_with($mail->link, config('apfspn.frontend.admin_url').'/reset-password?token='));
    }

    public function test_invited_admin_cannot_log_in_until_reset_then_can(): void
    {
        app(AdminInviteService::class)->invite('Ada Admin', 'ada@example.com', ['editor']);

        $this->api('POST', '/api/v1/admin/auth/login', ['email' => 'ada@example.com', 'password' => 'Anything123'])
            ->assertUnauthorized();

        $link = Mail::sent(AdminInviteMail::class)->first()->link;
        parse_str(parse_url($link, PHP_URL_QUERY), $query);

        $this->api('POST', '/api/v1/admin/auth/reset-password', [
            'reset_token' => $query['token'],
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertOk();

        $this->api('POST', '/api/v1/admin/auth/login', ['email' => 'ada@example.com', 'password' => 'NewPassword1'])
            ->assertOk()
            ->assertJsonPath('data.admin.must_reset_password', false)
            ->assertJsonPath('data.admin.roles', ['editor']);
    }

    public function test_invite_rejects_duplicate_email(): void
    {
        Admin::factory()->create(['email' => 'ada@example.com']);

        $this->expectExceptionMessage('An admin with this email already exists.');

        app(AdminInviteService::class)->invite('Ada Admin', 'ada@example.com', ['editor']);
    }

    public function test_super_admin_seeder_invites_once_from_config(): void
    {
        config(['apfspn.auth.super_admin' => ['name' => 'Root Admin', 'email' => 'root@apfspn.org']]);

        $this->seed(SuperAdminSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(1, Admin::where('email', 'root@apfspn.org')->count());
        $this->assertTrue(Admin::where('email', 'root@apfspn.org')->first()->hasRole('super_admin'));
        Mail::assertSent(AdminInviteMail::class, 1);
    }

    public function test_super_admin_seeder_skips_without_config(): void
    {
        config(['apfspn.auth.super_admin' => ['name' => null, 'email' => null]]);

        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(0, Admin::count());
        Mail::assertNothingSent();
    }
}

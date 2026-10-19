<?php

namespace Tests\Feature\AdminUsers;

use App\Enums\ePermission;
use App\Jobs\SendAdminDisabledEmail;
use App\Jobs\SendOtpEmail;
use App\Mail\AdminDisabledMail;
use App\Models\Admin;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Event;
use App\Services\AdminUsers\AdminUserMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class AdminSafetyRulesTest extends TestCase
{
    use InteractsWithAdminUsers, InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_admin_cannot_disable_delete_or_change_the_role_of_themselves(): void
    {
        $actor = $this->adminWithRole('super_admin');
        $this->adminWithRole('super_admin');
        $token = $this->tokenFor($actor);

        $this->api('POST', $this->userUrl($actor, 'disable'), [], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'cannot_modify_self');

        $this->api('DELETE', $this->userUrl($actor), [], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'cannot_modify_self');

        $this->api('PUT', $this->userUrl($actor), ['role_uuid' => $this->role('editor')->uuid], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'cannot_modify_self');

        $this->assertTrue($actor->fresh()->isActive());
        $this->assertTrue($actor->fresh()->hasRole('super_admin'));
    }

    public function test_last_active_super_admin_cannot_be_disabled_deleted_or_demoted(): void
    {
        $actor = $this->adminWithRole('super_admin');
        $last = $this->adminWithRole('super_admin');
        $this->adminWithRole('super_admin', ['must_reset_password' => true]);
        $this->adminWithRole('super_admin', ['status' => 'disabled']);
        $token = $this->tokenFor($actor);

        $this->api('POST', $this->userUrl($last, 'disable'), [], $token)->assertOk();
        $this->api('POST', $this->userUrl($last, 'enable'), [], $token)->assertOk();

        $actor->syncRoles(['editor']);
        $actor->givePermissionTo(ePermission::values());
        $this->app['auth']->forgetGuards();

        $this->api('POST', $this->userUrl($last, 'disable'), [], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'last_super_admin');

        $this->api('DELETE', $this->userUrl($last), [], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'last_super_admin');

        $this->api('PUT', $this->userUrl($last), ['role_uuid' => $this->role('editor')->uuid], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'last_super_admin');

        $this->assertTrue($last->fresh()->isActive());
        $this->assertTrue($last->fresh()->hasRole('super_admin'));
    }

    public function test_disable_revokes_tokens_audits_and_queues_email(): void
    {
        $token = $this->tokenFor($this->adminWithRole('super_admin'));
        $editor = $this->adminWithRole('editor');
        $editorTokens = $this->adminTokens($editor);

        $this->api('POST', $this->userUrl($editor, 'disable'), [], $token)
            ->assertOk()
            ->assertJsonPath('data.account_state', 'disabled');

        $this->assertSame(0, $editor->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.disabled', 'auditable_uuid' => $editor->uuid]);
        Queue::assertPushed(SendAdminDisabledEmail::class, fn (SendAdminDisabledEmail $job) => $job->adminUuid === $editor->uuid);

        $this->api('GET', '/api/v1/admin/auth/me', [], $editorTokens['access_token'])->assertUnauthorized();

        $this->api('POST', $this->userUrl($editor, 'disable'), [], $token)->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.disabled')->count());
        Queue::assertPushed(SendAdminDisabledEmail::class, 1);
    }

    public function test_disabled_email_is_sent_only_while_still_disabled(): void
    {
        Mail::fake();
        $admin = Admin::factory()->disabled()->create();

        app(AdminUserMailService::class)->sendDisabled($admin->uuid);
        Mail::assertSent(AdminDisabledMail::class, fn (AdminDisabledMail $mail) => $mail->hasTo($admin->email));

        $this->assertStringContainsString('has been disabled', (new AdminDisabledMail('Ada'))->render());

        $admin->update(['status' => 'active']);
        app(AdminUserMailService::class)->sendDisabled($admin->uuid);
        Mail::assertSent(AdminDisabledMail::class, 1);
    }

    public function test_disabled_admin_is_refused_at_login_otp_refresh_and_on_existing_tokens(): void
    {
        $admin = $this->adminWithRole('editor');
        $tokens = $this->adminTokens($admin);
        $admin->update(['status' => 'disabled']);

        $this->api('GET', '/api/v1/admin/auth/me', [], $tokens['access_token'])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'account_disabled');

        $this->api('POST', '/api/v1/admin/auth/refresh', [], $tokens['refresh_token'])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'account_disabled');

        $this->api('POST', '/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => 'Password123'])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'account_disabled');

        config(['apfspn.auth.admin_two_factor' => true]);
        $admin->update(['status' => 'active']);
        $challenge = $this->api('POST', '/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => 'Password123'])
            ->assertOk()
            ->json('data.challenge.challenge_token');
        $admin->update(['status' => 'disabled']);

        $this->api('POST', '/api/v1/admin/auth/verify-otp', ['challenge_token' => $challenge, 'code' => $this->lastOtp()])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'account_disabled');
    }

    public function test_forgot_password_for_a_disabled_admin_is_a_decoy(): void
    {
        $admin = Admin::factory()->disabled()->create();

        $disabled = $this->api('POST', '/api/v1/admin/auth/forgot-password', ['email' => $admin->email])->assertOk();
        $unknown = $this->api('POST', '/api/v1/admin/auth/forgot-password', ['email' => 'nobody@apfspn.test'])->assertOk();

        $this->assertSame(array_keys($unknown->json('data')), array_keys($disabled->json('data')));
        $this->assertSame($unknown->json('data.mode'), $disabled->json('data.mode'));
        Queue::assertNotPushed(SendOtpEmail::class);
        $this->assertDatabaseHas('auth_challenges', ['subject_uuid' => null, 'purpose' => 'password_reset']);
        $this->assertDatabaseMissing('auth_challenges', ['subject_uuid' => $admin->uuid]);
    }

    public function test_delete_is_blocked_by_activity_and_otherwise_removes_the_admin(): void
    {
        $token = $this->tokenFor($this->adminWithRole('super_admin'));
        $busy = $this->adminWithRole('editor');
        AuditLog::create(['admin_uuid' => $busy->uuid, 'action' => 'article.published']);
        Article::factory()->create(['author_admin_uuid' => $busy->uuid]);
        Event::factory()->create(['created_by_admin_uuid' => $busy->uuid]);

        $this->api('DELETE', $this->userUrl($busy), [], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'blocked')
            ->assertJsonPath('data.counts', ['audit_logs' => 1, 'articles' => 1, 'events' => 1]);

        $idle = $this->adminWithRole('author', ['must_reset_password' => true]);
        $this->api('DELETE', $this->userUrl($idle), [], $token)->assertOk();

        $this->assertDatabaseMissing('admins', ['uuid' => $idle->uuid]);
        $this->assertDatabaseMissing('model_has_roles', ['model_uuid' => $idle->uuid]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.deleted', 'auditable_uuid' => $idle->uuid]);
        $this->api('GET', $this->userUrl($idle), [], $token)->assertNotFound();
    }
}

<?php

namespace Tests\Feature\AdminUsers;

use App\Jobs\SendAdminInviteEmail;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use InteractsWithAdminUsers, InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_super_admin_invites_an_admin_with_exactly_one_role(): void
    {
        $actor = $this->adminWithRole('super_admin');

        $uuid = $this->api('POST', '/api/v1/admin/users', [
            'name' => 'Ngozi Editor',
            'email' => ' Ngozi@Example.com ',
            'role_uuid' => $this->role('editor')->uuid,
        ], $this->tokenFor($actor))
            ->assertCreated()
            ->assertJsonPath('data.email', 'ngozi@example.com')
            ->assertJsonPath('data.account_state', 'invited')
            ->assertJsonPath('data.roles', ['editor'])
            ->json('data.uuid');

        $invited = Admin::findOrFail($uuid);
        $this->assertSame($actor->uuid, $invited->created_by_admin_uuid);
        Queue::assertPushed(SendAdminInviteEmail::class, fn (SendAdminInviteEmail $job) => $job->email === 'ngozi@example.com');
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.invited', 'auditable_uuid' => $uuid, 'admin_uuid' => $actor->uuid]);
        $this->assertStringStartsWith('sha256:', AuditLog::where('action', 'admin.invited')->first()->request_body['email']);
    }

    public function test_invite_validates_role_and_duplicate_email(): void
    {
        $token = $this->tokenFor($this->adminWithRole('super_admin'));
        Admin::factory()->create(['email' => 'taken@example.com']);

        $this->api('POST', '/api/v1/admin/users', ['name' => 'X', 'email' => 'new@example.com', 'role_uuid' => fake()->uuid()], $token)
            ->assertUnprocessable()
            ->assertJsonStructure(['data' => ['role_uuid']]);

        $this->api('POST', '/api/v1/admin/users', ['name' => 'X', 'email' => 'new@example.com'], $token)
            ->assertUnprocessable()
            ->assertJsonStructure(['data' => ['role_uuid']]);

        $this->api('POST', '/api/v1/admin/users', [
            'name' => 'X', 'email' => 'new@example.com', 'role_uuid' => $this->role('editor')->uuid,
            'password' => 'Secret123!', 'status' => 'active', 'roles' => ['super_admin'],
        ], $token)
            ->assertUnprocessable()
            ->assertJsonStructure(['data' => ['password', 'status', 'roles']]);

        $this->api('POST', '/api/v1/admin/users', ['name' => 'X', 'email' => 'taken@example.com', 'role_uuid' => $this->role('editor')->uuid], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'email_taken');
    }

    public function test_resend_invite_only_for_invited_admins(): void
    {
        $token = $this->tokenFor($this->adminWithRole('super_admin'));
        $invited = $this->adminWithRole('author', ['must_reset_password' => true]);
        $active = $this->adminWithRole('author');

        $this->api('POST', $this->userUrl($invited, 'resend-invite'), [], $token)
            ->assertOk()
            ->assertJsonPath('data.account_state', 'invited');
        Queue::assertPushed(SendAdminInviteEmail::class, fn (SendAdminInviteEmail $job) => $job->email === $invited->email);

        $this->api('POST', $this->userUrl($active, 'resend-invite'), [], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'admin_not_invited')
            ->assertJsonPath('data.account_state', 'active');
    }

    public function test_list_filters_by_state_role_and_search(): void
    {
        $actor = $this->adminWithRole('super_admin', ['name' => 'Aaa Root']);
        $this->adminWithRole('editor', ['name' => 'Bola Editor', 'email' => 'bola@example.com']);
        $this->adminWithRole('author', ['must_reset_password' => true]);
        $this->adminWithRole('author', ['status' => 'disabled']);
        $token = $this->tokenFor($actor);

        $this->api('GET', '/api/v1/admin/users', [], $token)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 4)
            ->assertJsonPath('data.items.0.name', 'Aaa Root');

        $this->api('GET', '/api/v1/admin/users?account_state=invited', [], $token)->assertJsonPath('data.pagination.total', 1);
        $this->api('GET', '/api/v1/admin/users?account_state=disabled', [], $token)->assertJsonPath('data.pagination.total', 1);
        $this->api('GET', '/api/v1/admin/users?account_state=active', [], $token)->assertJsonPath('data.pagination.total', 2);
        $this->api('GET', '/api/v1/admin/users?role_uuid='.$this->role('author')->uuid, [], $token)->assertJsonPath('data.pagination.total', 2);
        $this->api('GET', '/api/v1/admin/users?search=bola@', [], $token)->assertJsonPath('data.items.0.name', 'Bola Editor');
        $this->api('GET', '/api/v1/admin/users?account_state=gone', [], $token)->assertUnprocessable();
    }

    public function test_show_and_404(): void
    {
        $token = $this->tokenFor($this->adminWithRole('super_admin'));
        $editor = $this->adminWithRole('editor');

        $this->api('GET', $this->userUrl($editor), [], $token)
            ->assertOk()
            ->assertJsonPath('data.uuid', $editor->uuid)
            ->assertJsonPath('data.roles', ['editor'])
            ->assertJsonMissingPath('data.password');

        $this->api('GET', '/api/v1/admin/users/'.fake()->uuid(), [], $token)->assertNotFound();
    }

    public function test_update_name_and_role_with_audit(): void
    {
        $token = $this->tokenFor($this->adminWithRole('super_admin'));
        $admin = $this->adminWithRole('author', ['name' => 'Old Name']);

        $this->api('PUT', $this->userUrl($admin), ['name' => 'New Name', 'role_uuid' => $this->role('editor')->uuid], $token)
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.roles', ['editor']);

        $log = AuditLog::where('action', 'admin.updated')->firstOrFail();
        $this->assertEqualsCanonicalizing(['name', 'role'], $log->changes['changed_fields']);
        $this->assertSame('author', $log->changes['previous']['role']);
        $this->assertSame('Old Name', $log->changes['previous']['name']);

        $this->api('PUT', $this->userUrl($admin), ['name' => 'New Name'], $token)->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.updated')->count());

        $this->api('PUT', $this->userUrl($admin), ['email' => 'x@example.com'], $token)
            ->assertUnprocessable()
            ->assertJsonStructure(['data' => ['email']]);
    }

    public function test_admin_can_rename_themselves(): void
    {
        $actor = $this->adminWithRole('super_admin');

        $this->api('PUT', $this->userUrl($actor), ['name' => 'Renamed Me'], $this->tokenFor($actor))
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Me');
    }

    public function test_audit_trail_lists_actions_by_that_admin_newest_first(): void
    {
        $actor = $this->adminWithRole('super_admin');
        $editor = $this->adminWithRole('editor');
        AuditLog::create(['admin_uuid' => $editor->uuid, 'action' => 'article.created', 'created_at' => now()->subDay()]);
        AuditLog::create(['admin_uuid' => $editor->uuid, 'action' => 'article.published', 'created_at' => now()]);
        AuditLog::create(['admin_uuid' => $actor->uuid, 'action' => 'member.updated']);

        $this->api('GET', $this->userUrl($editor, 'audit-logs'), [], $this->tokenFor($actor))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.items.0.action', 'article.published')
            ->assertJsonPath('data.items.1.action', 'article.created');
    }

    public function test_enable_restores_a_disabled_admin(): void
    {
        $token = $this->tokenFor($this->adminWithRole('super_admin'));
        $admin = $this->adminWithRole('editor', ['status' => 'disabled']);

        $this->api('POST', $this->userUrl($admin, 'enable'), [], $token)
            ->assertOk()
            ->assertJsonPath('data.account_state', 'active');

        $this->api('POST', $this->userUrl($admin, 'enable'), [], $token)->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.enabled')->count());
        $this->adminTokens($admin->fresh());
    }

    public function test_custom_role_holder_cannot_grant_more_than_they_hold(): void
    {
        $this->role('editor');
        $manager = Role::create(['name' => 'people_manager', 'guard_name' => 'api']);
        $manager->givePermissionTo(['admins.view', 'admins.create', 'admins.update', 'dashboard.view']);
        $actor = tap(Admin::factory()->create())->assignRole('people_manager');
        $token = $this->tokenFor($actor);

        $this->api('POST', '/api/v1/admin/users', ['name' => 'Escalate', 'email' => 'esc@example.com', 'role_uuid' => $this->role('super_admin')->uuid], $token)
            ->assertForbidden()
            ->assertJsonPath('data.code', 'role_exceeds_own_permissions');
        $this->assertDatabaseMissing('admins', ['email' => 'esc@example.com']);

        $editor = $this->adminWithRole('editor');
        $this->api('POST', $this->userUrl($editor, 'disable'), [], $token)
            ->assertForbidden()
            ->assertJsonPath('data.code', 'role_exceeds_own_permissions');

        $this->api('PUT', $this->userUrl($actor), ['role_uuid' => $this->role('super_admin')->uuid], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'cannot_modify_self');
    }
}

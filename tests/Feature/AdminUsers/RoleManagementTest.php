<?php

namespace Tests\Feature\AdminUsers;

use App\Enums\ePermission;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use InteractsWithAdminUsers, InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_list_shows_permissions_admin_counts_and_flags(): void
    {
        $token = $this->tokenFor($this->adminWithRole('super_admin'));
        $this->adminWithRole('editor');
        $this->adminWithRole('editor');

        $roles = collect($this->api('GET', '/api/v1/admin/roles', [], $token)->assertOk()->json('data'))->keyBy('name');

        $this->assertCount(6, $roles);
        $this->assertSame(2, $roles['editor']['admins_count']);
        $this->assertSame(1, $roles['super_admin']['admins_count']);
        $this->assertTrue($roles['super_admin']['locked']);
        $this->assertTrue($roles['editor']['built_in']);
        $this->assertFalse($roles['editor']['locked']);
        $this->assertContains('articles.publish', $roles['editor']['permissions']);
        $this->assertCount(count(ePermission::cases()), $roles['super_admin']['permissions']);
    }

    public function test_permissions_catalogue_is_grouped_by_module(): void
    {
        $groups = collect($this->api('GET', '/api/v1/admin/roles/permissions', [], $this->tokenFor($this->adminWithRole('super_admin')))
            ->assertOk()
            ->json('data'))->keyBy('module');

        $this->assertSame(count(ePermission::cases()), $groups->sum(fn ($group) => count($group['permissions'])));
        $this->assertSame('Event Registrations', $groups['event_registrations']['label']);
        $this->assertContains(['name' => 'articles.publish', 'label' => 'Articles Publish'], $groups['articles']['permissions']);
    }

    public function test_create_show_update_and_delete_a_custom_role(): void
    {
        $token = $this->tokenFor($this->adminWithRole('super_admin'));

        $uuid = $this->api('POST', '/api/v1/admin/roles', ['name' => 'press_officer', 'permissions' => ['articles.view', 'articles.publish']], $token)
            ->assertCreated()
            ->assertJsonPath('data.name', 'press_officer')
            ->assertJsonPath('data.built_in', false)
            ->assertJsonPath('data.permissions', ['articles.publish', 'articles.view'])
            ->json('data.uuid');
        $role = Role::findOrFail($uuid);

        $this->api('GET', $this->roleUrl($role), [], $token)->assertOk()->assertJsonPath('data.admins_count', 0);

        $this->api('PUT', $this->roleUrl($role), ['name' => 'press_lead', 'permissions' => ['articles.view']], $token)
            ->assertOk()
            ->assertJsonPath('data.name', 'press_lead')
            ->assertJsonPath('data.permissions', ['articles.view']);

        $log = AuditLog::where('action', 'role.updated')->firstOrFail();
        $this->assertEqualsCanonicalizing(['name', 'permissions'], $log->changes['changed_fields']);
        $this->assertSame(['articles.publish', 'articles.view'], $log->changes['previous']['permissions']);

        $this->api('PUT', $this->roleUrl($role), ['permissions' => ['articles.view']], $token)->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'role.updated')->count());

        $this->api('DELETE', $this->roleUrl($role), [], $token)->assertOk();
        $this->assertDatabaseMissing('roles', ['uuid' => $uuid]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.deleted', 'auditable_uuid' => $uuid]);
    }

    public function test_role_validation(): void
    {
        $token = $this->tokenFor($this->adminWithRole('super_admin'));

        $this->api('POST', '/api/v1/admin/roles', ['name' => 'Bad Name!', 'permissions' => ['payments.refund']], $token)
            ->assertUnprocessable()
            ->assertJsonStructure(['data' => ['name', 'permissions.0']]);

        $this->api('POST', '/api/v1/admin/roles', ['name' => 'editor', 'permissions' => []], $token)
            ->assertUnprocessable()
            ->assertJsonStructure(['data' => ['name', 'permissions']]);

        $this->api('POST', '/api/v1/admin/roles', ['name' => 'valid_name', 'permissions' => ['articles.view'], 'guard_name' => 'web'], $token)
            ->assertUnprocessable()
            ->assertJsonStructure(['data' => ['guard_name']]);
    }

    public function test_super_admin_role_is_locked(): void
    {
        $token = $this->tokenFor($this->adminWithRole('super_admin'));
        $role = $this->role('super_admin');

        $this->api('PUT', $this->roleUrl($role), ['permissions' => ['dashboard.view']], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'role_locked');

        $this->api('DELETE', $this->roleUrl($role), [], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'role_locked');

        $this->assertCount(count(ePermission::cases()), $role->fresh()->permissions);
    }

    public function test_built_in_roles_keep_their_names_but_permissions_are_editable(): void
    {
        $token = $this->tokenFor($this->adminWithRole('super_admin'));
        $editor = $this->role('editor');

        $this->api('PUT', $this->roleUrl($editor), ['name' => 'chief_editor'], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'built_in_role_name_locked');

        $this->api('PUT', $this->roleUrl($editor), ['permissions' => ['dashboard.view', 'articles.view']], $token)->assertOk();
        $this->assertEqualsCanonicalizing(['dashboard.view', 'articles.view'], $editor->fresh()->permissions->pluck('name')->all());
    }

    public function test_role_edits_made_through_the_api_survive_a_seeder_rerun(): void
    {
        $token = $this->tokenFor($this->adminWithRole('super_admin'));

        $this->api('PUT', $this->roleUrl($this->role('editor')), ['permissions' => ['dashboard.view', 'articles.view']], $token)->assertOk();
        $this->api('PUT', $this->roleUrl($this->role('author')), ['permissions' => ['dashboard.view', 'articles.view', 'articles.create', 'articles.update', 'articles.submit', 'events.view', 'events.create', 'events.update', 'members.view']], $token)->assertOk();
        $this->api('DELETE', $this->roleUrl($this->role('membership_coordinator')), [], $token)->assertOk();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertEqualsCanonicalizing(['dashboard.view', 'articles.view'], Role::findByName('editor', 'api')->permissions->pluck('name')->all());
        $this->assertContains('members.view', Role::findByName('author', 'api')->permissions->pluck('name')->all());
        $this->assertNull(Role::where('name', 'membership_coordinator')->first());
    }

    public function test_delete_is_blocked_while_admins_hold_the_role(): void
    {
        $token = $this->tokenFor($this->adminWithRole('super_admin'));
        $this->adminWithRole('author');
        $this->adminWithRole('author');

        $this->api('DELETE', $this->roleUrl($this->role('author')), [], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'blocked')
            ->assertJsonPath('data.counts', ['admins' => 2]);
    }

    public function test_role_editors_cannot_grant_permissions_they_do_not_hold(): void
    {
        $this->role('editor');
        Role::create(['name' => 'role_manager', 'guard_name' => 'api'])->givePermissionTo(['roles.view', 'roles.create', 'roles.update', 'articles.view']);
        $token = $this->tokenFor(tap(Admin::factory()->create())->assignRole('role_manager'));

        $this->api('POST', '/api/v1/admin/roles', ['name' => 'sneaky', 'permissions' => ['members.delete']], $token)
            ->assertForbidden()
            ->assertJsonPath('data.code', 'role_exceeds_own_permissions')
            ->assertJsonPath('data.permissions', ['members.delete']);

        $this->api('PUT', $this->roleUrl(Role::findByName('role_manager', 'api')), ['permissions' => ['roles.view', 'roles.create', 'roles.update', 'articles.view', 'admins.create']], $token)
            ->assertForbidden()
            ->assertJsonPath('data.code', 'role_exceeds_own_permissions');

        $editor = $this->role('editor');
        $this->api('PUT', $this->roleUrl($editor), ['permissions' => ['articles.view']], $token)->assertOk();
    }
}

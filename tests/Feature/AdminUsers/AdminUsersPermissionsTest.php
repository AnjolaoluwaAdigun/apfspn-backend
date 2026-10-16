<?php

namespace Tests\Feature\AdminUsers;

use App\Enums\ePermission;
use App\Models\Admin;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class AdminUsersPermissionsTest extends TestCase
{
    use InteractsWithAdminUsers, InteractsWithAuth, RefreshDatabase;

    private const ENDPOINTS = [
        'list admins' => ['GET', 'users', ePermission::AdminsView],
        'show admin' => ['GET', 'users/{admin}', ePermission::AdminsView],
        'invite admin' => ['POST', 'users', ePermission::AdminsCreate],
        'resend invite' => ['POST', 'users/{admin}/resend-invite', ePermission::AdminsCreate],
        'update admin' => ['PUT', 'users/{admin}', ePermission::AdminsUpdate],
        'disable admin' => ['POST', 'users/{admin}/disable', ePermission::AdminsUpdate],
        'enable admin' => ['POST', 'users/{admin}/enable', ePermission::AdminsUpdate],
        'delete admin' => ['DELETE', 'users/{admin}', ePermission::AdminsDelete],
        'audit trail' => ['GET', 'users/{admin}/audit-logs', ePermission::AuditLogsView],
        'list roles' => ['GET', 'roles', ePermission::RolesView],
        'permission catalogue' => ['GET', 'roles/permissions', ePermission::RolesView],
        'show role' => ['GET', 'roles/{role}', ePermission::RolesView],
        'create role' => ['POST', 'roles', ePermission::RolesCreate],
        'update role' => ['PUT', 'roles/{role}', ePermission::RolesUpdate],
        'delete role' => ['DELETE', 'roles/{role}', ePermission::RolesDelete],
    ];

    public static function forbiddenCases(): array
    {
        $cases = [];

        foreach (RolesAndPermissionsSeeder::roleMap() as $role => $permissions) {
            foreach (self::ENDPOINTS as $name => [$method, $path, $permission]) {
                if (! in_array($permission, $permissions, true)) {
                    $cases[$role.' cannot '.$name] = [$role, $method, $path];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('forbiddenCases')]
    public function test_roles_without_the_permission_get_403(string $role, string $method, string $path): void
    {
        Queue::fake();
        $admin = $this->adminWithRole($role);
        $target = Admin::factory()->create();
        $url = '/api/v1/admin/'.str_replace(['{admin}', '{role}'], [$target->uuid, $this->role('author')->uuid], $path);

        $this->api($method, $url, [], $this->tokenFor($admin))
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not allowed to perform this action.');
    }

    public function test_only_super_admin_holds_admin_and_role_permissions(): void
    {
        $managed = preg_grep('/^(admins|roles|audit_logs)\./', ePermission::values());

        foreach (RolesAndPermissionsSeeder::roleMap() as $role => $permissions) {
            $held = array_map(fn (ePermission $permission) => $permission->value, $permissions);

            $this->assertSame($role === 'super_admin', array_intersect($managed, $held) !== [], $role);
        }

        $this->assertCount(9, $managed);
    }
}

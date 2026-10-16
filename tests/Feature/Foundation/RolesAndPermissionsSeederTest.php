<?php

namespace Tests\Feature\Foundation;

use App\Enums\ePermission;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    private const GUARD = RolesAndPermissionsSeeder::GUARD;

    protected function tearDown(): void
    {
        ShrinkableRoleMapSeeder::$withheld = [];

        parent::tearDown();
    }

    public function test_first_run_creates_every_permission_and_built_in_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEqualsCanonicalizing(
            ePermission::values(),
            Permission::where('guard_name', self::GUARD)->pluck('name')->all(),
        );

        foreach (RolesAndPermissionsSeeder::roleMap() as $roleName => $permissions) {
            $this->assertEqualsCanonicalizing(
                array_map(fn (ePermission $p) => $p->value, $permissions),
                $this->permissionsOf($roleName),
            );
        }

        $this->assertEqualsCanonicalizing(ePermission::values(), $this->permissionsOf('super_admin'));
    }

    public function test_permissions_not_in_the_enum_are_pruned(): void
    {
        Permission::create(['name' => 'deals.view', 'guard_name' => self::GUARD]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseMissing('permissions', ['name' => 'deals.view']);
    }

    public function test_rerun_never_undoes_admin_changes_to_built_in_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $author = $this->role('author');
        $author->revokePermissionTo(ePermission::ArticlesUpdate->value);
        $author->givePermissionTo(ePermission::MembersView->value);

        $this->seed(RolesAndPermissionsSeeder::class);

        $permissions = $this->permissionsOf('author');
        $this->assertNotContains(ePermission::ArticlesUpdate->value, $permissions);
        $this->assertContains(ePermission::MembersView->value, $permissions);
    }

    public function test_rerun_adds_only_permissions_new_to_the_map(): void
    {
        ShrinkableRoleMapSeeder::$withheld = [ePermission::ArticlesSubmit];
        $this->seed(ShrinkableRoleMapSeeder::class);

        $this->assertNotContains(ePermission::ArticlesSubmit->value, $this->permissionsOf('author'));

        $this->role('author')->revokePermissionTo(ePermission::ArticlesView->value);

        ShrinkableRoleMapSeeder::$withheld = [];
        $this->seed(ShrinkableRoleMapSeeder::class);

        $permissions = $this->permissionsOf('author');
        $this->assertContains(ePermission::ArticlesSubmit->value, $permissions);
        $this->assertNotContains(ePermission::ArticlesView->value, $permissions);
    }

    public function test_events_cancel_is_held_only_by_super_admin_and_events_coordinator(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $holders = Role::where('guard_name', self::GUARD)->get()
            ->filter(fn (Role $role) => $role->hasPermissionTo(ePermission::EventsCancel->value))
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing(['super_admin', 'events_coordinator'], $holders);
    }

    public function test_events_cancel_reaches_existing_roles_without_undoing_admin_edits(): void
    {
        ShrinkableRoleMapSeeder::$withheld = [ePermission::EventsCancel];
        $this->seed(ShrinkableRoleMapSeeder::class);

        $this->assertNotContains(ePermission::EventsCancel->value, $this->permissionsOf('events_coordinator'));

        $coordinator = $this->role('events_coordinator');
        $coordinator->revokePermissionTo(ePermission::EventsDelete->value);
        $this->role('editor')->givePermissionTo(ePermission::EventRegistrationsView->value);

        ShrinkableRoleMapSeeder::$withheld = [];
        $this->seed(ShrinkableRoleMapSeeder::class);

        $coordinatorPermissions = $this->permissionsOf('events_coordinator');
        $this->assertContains(ePermission::EventsCancel->value, $coordinatorPermissions);
        $this->assertNotContains(ePermission::EventsDelete->value, $coordinatorPermissions);
        $this->assertContains(ePermission::EventsCancel->value, $this->permissionsOf('super_admin'));

        $editorPermissions = $this->permissionsOf('editor');
        $this->assertNotContains(ePermission::EventsCancel->value, $editorPermissions);
        $this->assertContains(ePermission::EventRegistrationsView->value, $editorPermissions);
    }

    public function test_rerun_does_not_recreate_a_deleted_built_in_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->role('editor')->delete();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseMissing('roles', ['name' => 'editor', 'guard_name' => self::GUARD]);
    }

    private function role(string $name): Role
    {
        return Role::findByName($name, self::GUARD);
    }

    private function permissionsOf(string $roleName): array
    {
        return $this->role($roleName)->permissions()->pluck('name')->all();
    }
}

class ShrinkableRoleMapSeeder extends RolesAndPermissionsSeeder
{
    public static array $withheld = [];

    public static function roleMap(): array
    {
        return array_map(
            fn (array $permissions) => array_values(array_filter($permissions, fn (ePermission $p) => ! in_array($p, static::$withheld, true))),
            parent::roleMap(),
        );
    }
}

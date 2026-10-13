<?php

namespace Database\Seeders;

use App\Enums\ePermission as P;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SeededRolePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public const GUARD = 'api';

    public static function roleMap(): array
    {
        return [
            'super_admin' => P::cases(),
            'association_manager' => [
                P::DashboardView, P::MembersView, P::EventsView, P::EventRegistrationsView,
                P::ArticlesView, P::ArticlesPublish,
            ],
            'editor' => [
                P::DashboardView,
                P::ArticlesView, P::ArticlesCreate, P::ArticlesUpdate, P::ArticlesPublish, P::ArticlesArchive, P::ArticlesDelete,
                P::EventsView, P::EventsCreate, P::EventsUpdate, P::EventsPublish,
            ],
            'author' => [
                P::DashboardView,
                P::ArticlesView, P::ArticlesCreate, P::ArticlesUpdate, P::ArticlesSubmit,
                P::EventsView, P::EventsCreate, P::EventsUpdate,
            ],
            'events_coordinator' => [
                P::DashboardView,
                P::EventsView, P::EventsCreate, P::EventsUpdate, P::EventsDelete, P::EventsPublish,
                P::EventRegistrationsView, P::EventRegistrationsUpdate, P::EventRegistrationsExport,
            ],
            'membership_coordinator' => [
                P::DashboardView,
                P::MembersView, P::MembersUpdate, P::MembersApprove, P::MembersSuspend, P::MembersExport,
            ],
        ];
    }

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        DB::transaction(function () {
            $this->syncPermissions();
            $this->applyRoleMap();
        });

        $registrar->forgetCachedPermissions();
    }

    private function syncPermissions(): void
    {
        foreach (P::values() as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        Permission::where('guard_name', self::GUARD)->whereNotIn('name', P::values())->delete();
        SeededRolePermission::where('guard_name', self::GUARD)->whereNotIn('permission_name', P::values())->delete();
    }

    private function applyRoleMap(): void
    {
        foreach (static::roleMap() as $roleName => $permissions) {
            $applied = SeededRolePermission::where('guard_name', self::GUARD)
                ->where('role_name', $roleName)
                ->pluck('permission_name')
                ->all();

            $pending = array_values(array_diff(array_map(fn (P $p) => $p->value, $permissions), $applied));

            if ($pending === []) {
                continue;
            }

            $role = Role::where('name', $roleName)->where('guard_name', self::GUARD)->first();

            if (! $role && $applied !== []) {
                continue;
            }

            $role ??= Role::create(['name' => $roleName, 'guard_name' => self::GUARD]);
            $role->givePermissionTo($pending);

            foreach ($pending as $permission) {
                SeededRolePermission::create([
                    'role_name' => $roleName,
                    'permission_name' => $permission,
                    'guard_name' => self::GUARD,
                    'applied_at' => now(),
                ]);
            }
        }
    }
}

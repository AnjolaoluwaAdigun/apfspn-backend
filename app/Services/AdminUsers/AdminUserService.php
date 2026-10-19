<?php

namespace App\Services\AdminUsers;

use App\Enums\AdminAccountState;
use App\Enums\AdminStatus;
use App\Exceptions\ApiException;
use App\Jobs\SendAdminDisabledEmail;
use App\Models\Admin;
use App\Models\Role;
use App\Repositories\Contracts\AdminRepositoryInterface;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\AdminInviteService;
use App\Services\Auth\TokenService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminUserService
{
    public const SUPER_ADMIN_ROLE = 'super_admin';

    private const DEFAULT_PER_PAGE = 15;

    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly AdminRepositoryInterface $admins,
        private readonly RoleRepositoryInterface $roles,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly ArticleRepositoryInterface $articles,
        private readonly EventRepositoryInterface $events,
        private readonly AdminInviteService $invites,
        private readonly TokenService $tokens,
        private readonly PrivilegeGuard $privileges,
        private readonly AuditLogService $audit,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->admins->paginate($filters, $this->perPage($filters));
    }

    public function show(string $uuid): Admin
    {
        return $this->admins->findWithRoles($uuid);
    }

    public function auditTrail(string $uuid, array $filters): LengthAwarePaginator
    {
        return $this->auditLogs->paginateForAdmin($this->show($uuid)->getKey(), $this->perPage($filters));
    }

    public function invite(array $data, Admin $actor): Admin
    {
        $role = $this->role($data['role_uuid']);
        $this->privileges->assertCanGrantRole($actor, $role);

        return $this->show($this->invites->invite($data['name'], $data['email'], [$role->name])->getKey());
    }

    public function resendInvite(string $uuid, Admin $actor): Admin
    {
        $admin = DB::transaction(function () use ($uuid, $actor) {
            $admin = $this->locked($uuid);
            $this->privileges->assertCanManage($actor, $admin);

            if ($admin->accountState() !== AdminAccountState::Invited) {
                throw new ApiException('Only invited admins who have not set a password can be sent a new invite.', 422, [
                    'code' => 'admin_not_invited',
                    'account_state' => $admin->accountState()->value,
                ]);
            }

            $this->audit->record('admin.invite_resent', $admin);

            return $admin;
        }, self::TRANSACTION_ATTEMPTS);

        $this->invites->sendInvite($admin);

        return $this->show($uuid);
    }

    public function update(string $uuid, array $data, Admin $actor): Admin
    {
        DB::transaction(function () use ($uuid, $data, $actor) {
            $superAdmins = $this->admins->withRoleForUpdate(self::SUPER_ADMIN_ROLE);
            $admin = $this->locked($uuid);
            $currentRole = $this->admins->currentRole($admin);
            $newRole = isset($data['role_uuid']) ? $this->role($data['role_uuid']) : null;
            $roleChanged = $newRole !== null && $newRole->getKey() !== $currentRole?->getKey();

            if (isset($data['name'])) {
                $admin->name = $data['name'];
            }

            if ($roleChanged) {
                $this->assertNotSelf($admin, $actor, 'You cannot change your own role.');
                $this->privileges->assertCanManage($actor, $admin);
                $this->privileges->assertCanGrantRole($actor, $newRole);

                if ($currentRole?->name === self::SUPER_ADMIN_ROLE) {
                    $this->assertNotLastSuperAdmin($admin, $superAdmins);
                }
            }

            $changes = $this->audit->changeSet($admin);

            if ($roleChanged) {
                $changes['changed_fields'][] = 'role';
                $changes['previous']['role'] = $currentRole?->name;
            }

            if ($changes['changed_fields'] === []) {
                return;
            }

            $admin->updated_by_admin_uuid = $actor->getKey();
            $this->admins->save($admin);

            if ($roleChanged) {
                $this->admins->syncRoles($admin, [$newRole->name]);
            }

            $this->audit->record('admin.updated', $admin, $changes);
        }, self::TRANSACTION_ATTEMPTS);

        return $this->show($uuid);
    }

    public function disable(string $uuid, Admin $actor): Admin
    {
        DB::transaction(function () use ($uuid, $actor) {
            $superAdmins = $this->admins->withRoleForUpdate(self::SUPER_ADMIN_ROLE);
            $admin = $this->locked($uuid);

            $this->assertNotSelf($admin, $actor, 'You cannot disable your own account.');

            if ($admin->status === AdminStatus::Disabled) {
                return;
            }

            $this->privileges->assertCanManage($actor, $admin);
            $this->assertNotLastSuperAdmin($admin, $superAdmins);

            $admin->fill(['status' => AdminStatus::Disabled, 'updated_by_admin_uuid' => $actor->getKey()]);
            $changes = $this->audit->changeSet($admin);
            $this->admins->save($admin);
            $this->tokens->revokeAll($admin);
            $this->audit->record('admin.disabled', $admin, $changes);

            SendAdminDisabledEmail::dispatch($admin->getKey(), (string) $admin->updated_at->getTimestamp())->afterCommit();
        }, self::TRANSACTION_ATTEMPTS);

        return $this->show($uuid);
    }

    public function enable(string $uuid, Admin $actor): Admin
    {
        DB::transaction(function () use ($uuid, $actor) {
            $admin = $this->locked($uuid);

            if ($admin->status === AdminStatus::Active) {
                return;
            }

            $this->privileges->assertCanManage($actor, $admin);

            $admin->fill(['status' => AdminStatus::Active, 'updated_by_admin_uuid' => $actor->getKey()]);
            $changes = $this->audit->changeSet($admin);
            $this->admins->save($admin);
            $this->audit->record('admin.enabled', $admin, $changes);
        }, self::TRANSACTION_ATTEMPTS);

        return $this->show($uuid);
    }

    public function delete(string $uuid, Admin $actor): void
    {
        DB::transaction(function () use ($uuid, $actor) {
            $superAdmins = $this->admins->withRoleForUpdate(self::SUPER_ADMIN_ROLE);
            $admin = $this->locked($uuid);

            $this->assertNotSelf($admin, $actor, 'You cannot delete your own account.');
            $this->privileges->assertCanManage($actor, $admin);
            $this->assertNotLastSuperAdmin($admin, $superAdmins);

            $counts = [
                'audit_logs' => $this->auditLogs->countForAdmin($admin->getKey()),
                'articles' => $this->articles->countAuthoredBy($admin->getKey()),
                'events' => $this->events->countCreatedBy($admin->getKey()),
            ];

            if (array_sum($counts) > 0) {
                throw ApiException::blocked('This admin has activity on record and cannot be deleted. Disable the account instead.', $counts);
            }

            $this->audit->record('admin.deleted', $admin);
            $this->tokens->revokeAll($admin);
            $this->admins->delete($admin);
        }, self::TRANSACTION_ATTEMPTS);
    }

    private function assertNotSelf(Admin $admin, Admin $actor, string $message): void
    {
        if ($admin->getKey() === $actor->getKey()) {
            throw new ApiException($message, 422, ['code' => 'cannot_modify_self']);
        }
    }

    private function assertNotLastSuperAdmin(Admin $admin, Collection $superAdmins): void
    {
        $isActiveSuperAdmin = fn (Admin $candidate) => $candidate->isActive() && ! $candidate->must_reset_password;
        $active = $superAdmins->filter($isActiveSuperAdmin);

        if (! $active->contains(fn (Admin $candidate) => $candidate->getKey() === $admin->getKey())) {
            return;
        }

        if ($active->count() <= 1) {
            throw new ApiException('This is the last active super admin. Promote another admin to super admin first.', 422, [
                'code' => 'last_super_admin',
            ]);
        }
    }

    private function role(string $uuid): Role
    {
        return $this->roles->findByUuid($uuid)
            ?? throw (new ModelNotFoundException)->setModel(Role::class, [$uuid]);
    }

    private function locked(string $uuid): Admin
    {
        $admin = $this->admins->findForUpdate($uuid)
            ?? throw (new ModelNotFoundException)->setModel(Admin::class, [$uuid]);

        return $admin->load('roles');
    }

    private function perPage(array $filters): int
    {
        return (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
    }
}

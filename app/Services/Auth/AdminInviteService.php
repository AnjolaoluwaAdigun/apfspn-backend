<?php

namespace App\Services\Auth;

use App\Enums\AdminStatus;
use App\Exceptions\ApiException;
use App\Jobs\SendAdminInviteEmail;
use App\Models\Admin;
use App\Repositories\Contracts\AdminRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminInviteService
{
    public function __construct(
        private readonly AdminRepositoryInterface $admins,
        private readonly PasswordResetService $resets,
        private readonly AuditLogService $audit,
    ) {}

    public function invite(string $name, string $email, array $roles): Admin
    {
        $admin = DB::transaction(function () use ($name, $email, $roles) {
            if ($this->admins->findByEmail($email)) {
                throw new ApiException('An admin with this email already exists.', 422, ['code' => 'email_taken']);
            }

            $actorUuid = $this->audit->currentAdminUuid();

            $admin = $this->admins->create([
                'name' => $name,
                'email' => Str::lower(trim($email)),
                'password' => Str::random(64),
                'status' => AdminStatus::Active,
                'must_reset_password' => true,
                'created_by_admin_uuid' => $actorUuid,
                'updated_by_admin_uuid' => $actorUuid,
            ]);

            $this->admins->syncRoles($admin, $roles);
            $this->audit->record('admin.invited', $admin);

            return $admin;
        });

        $this->sendInvite($admin);

        return $admin;
    }

    public function sendInvite(Admin $admin): void
    {
        SendAdminInviteEmail::dispatchSync($admin->name, $admin->email, $this->inviteLink($admin));
    }

    public function inviteLink(Admin $admin): string
    {
        $token = $this->resets->issueToken($admin, config('apfspn.auth.invite_token_ttl_hours') * 60);

        return rtrim(config('apfspn.frontend.admin_url'), '/').'/reset-password?token='.urlencode($token);
    }
}

<?php

namespace Database\Seeders;

use App\Repositories\Contracts\AdminRepositoryInterface;
use App\Services\Auth\AdminInviteService;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(AdminRepositoryInterface $admins, AdminInviteService $invites): void
    {
        $name = config('apfspn.auth.super_admin.name');
        $email = config('apfspn.auth.super_admin.email');

        if (! $name || ! $email) {
            $this->command?->warn('SUPER_ADMIN_NAME or SUPER_ADMIN_EMAIL is not set; skipping super admin.');

            return;
        }

        if ($admins->findByEmail($email)) {
            $this->command?->info('Super admin already exists; skipping.');

            return;
        }

        $invites->invite($name, $email, ['super_admin']);
        $this->command?->info("Invite sent to {$email}.");
    }
}

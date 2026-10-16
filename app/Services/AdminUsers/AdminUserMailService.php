<?php

namespace App\Services\AdminUsers;

use App\Mail\AdminDisabledMail;
use App\Repositories\Contracts\AdminRepositoryInterface;
use Illuminate\Support\Facades\Mail;

class AdminUserMailService
{
    public function __construct(private readonly AdminRepositoryInterface $admins) {}

    public function sendDisabled(string $adminUuid): void
    {
        $admin = $this->admins->findByUuid($adminUuid);

        if ($admin === null || $admin->isActive()) {
            return;
        }

        Mail::to($admin->email)->send(new AdminDisabledMail($admin->name));
    }
}

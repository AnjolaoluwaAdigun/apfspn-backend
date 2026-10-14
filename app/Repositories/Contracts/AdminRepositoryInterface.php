<?php

namespace App\Repositories\Contracts;

use App\Models\Admin;

interface AdminRepositoryInterface extends AuthSubjectRepositoryInterface
{
    public function findByEmail(string $email): ?Admin;

    public function findByUuid(string $uuid): ?Admin;

    public function findForUpdate(string $uuid): ?Admin;

    public function create(array $attributes): Admin;

    public function update(Admin $admin, array $attributes): Admin;
}

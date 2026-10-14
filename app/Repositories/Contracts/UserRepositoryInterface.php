<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface extends AuthSubjectRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findByUuid(string $uuid): ?User;

    public function findForUpdate(string $uuid): ?User;

    public function update(User $user, array $attributes): User;
}

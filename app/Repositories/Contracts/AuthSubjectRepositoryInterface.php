<?php

namespace App\Repositories\Contracts;

use Illuminate\Foundation\Auth\User as Authenticatable;

interface AuthSubjectRepositoryInterface
{
    public function findByEmail(string $email): ?Authenticatable;

    public function findByUuid(string $uuid): ?Authenticatable;

    public function findForUpdate(string $uuid): ?Authenticatable;

    public function resetPassword(Authenticatable $subject, string $password): void;

    public function subjectClass(): string;
}

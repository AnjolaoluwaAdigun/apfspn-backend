<?php

namespace App\Repositories\Eloquent;

use App\Models\Admin;
use App\Repositories\Contracts\AdminRepositoryInterface;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class AdminRepository implements AdminRepositoryInterface
{
    public function subjectClass(): string
    {
        return Admin::class;
    }

    public function findByEmail(string $email): ?Admin
    {
        return Admin::where('email', Str::lower(trim($email)))->first();
    }

    public function findByUuid(string $uuid): ?Admin
    {
        return Admin::find($uuid);
    }

    public function findForUpdate(string $uuid): ?Admin
    {
        return Admin::whereKey($uuid)->lockForUpdate()->first();
    }

    public function resetPassword(Authenticatable $subject, string $password): void
    {
        $subject->update(['password' => $password, 'must_reset_password' => false]);
    }

    public function create(array $attributes): Admin
    {
        return Admin::create($attributes);
    }

    public function update(Admin $admin, array $attributes): Admin
    {
        $admin->update($attributes);

        return $admin;
    }
}

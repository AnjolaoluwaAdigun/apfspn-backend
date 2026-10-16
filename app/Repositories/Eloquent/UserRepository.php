<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class UserRepository implements UserRepositoryInterface
{
    public function subjectClass(): string
    {
        return User::class;
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', Str::lower(trim($email)))->first();
    }

    public function findByUuid(string $uuid): ?User
    {
        return User::find($uuid);
    }

    public function findForUpdate(string $uuid): ?User
    {
        return User::whereKey($uuid)->lockForUpdate()->first();
    }

    public function resetPassword(Authenticatable $subject, string $password): void
    {
        $subject->update(['password' => $password]);
    }

    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user;
    }

    public function withProfile(User $user): User
    {
        return $user->load(['membership', 'organisationProfile']);
    }

    public function tombstone(User $user, array $attributes): void
    {
        $user->update($attributes);
        $user->delete();
    }
}

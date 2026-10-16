<?php

namespace App\Repositories\Eloquent;

use App\Enums\AdminAccountState;
use App\Enums\AdminStatus;
use App\Models\Admin;
use App\Repositories\Contracts\AdminRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
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

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return Admin::with('roles')
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $term = '%'.addcslashes($search, '\%_').'%';

                $query->where(fn (Builder $inner) => $inner->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($filters['account_state'] ?? null, fn (Builder $query, string $state) => match (AdminAccountState::from($state)) {
                AdminAccountState::Disabled => $query->where('status', AdminStatus::Disabled),
                AdminAccountState::Invited => $query->where('status', AdminStatus::Active)->where('must_reset_password', true),
                AdminAccountState::Active => $query->where('status', AdminStatus::Active)->where('must_reset_password', false),
            })
            ->when($filters['role_uuid'] ?? null, fn (Builder $query, string $roleUuid) => $query->whereHas('roles', fn (Builder $roles) => $roles->whereKey($roleUuid)))
            ->orderBy('name')
            ->orderBy('uuid')
            ->paginate($perPage);
    }

    public function findWithRoles(string $uuid): Admin
    {
        return Admin::with('roles')->findOrFail($uuid);
    }

    public function withRoleForUpdate(string $role): Collection
    {
        return Admin::whereHas('roles', fn (Builder $roles) => $roles->where('name', $role))
            ->orderBy('uuid')
            ->lockForUpdate()
            ->get();
    }

    public function save(Admin $admin): Admin
    {
        $admin->save();

        return $admin;
    }

    public function delete(Admin $admin): void
    {
        $admin->syncRoles([]);
        $admin->delete();
    }
}

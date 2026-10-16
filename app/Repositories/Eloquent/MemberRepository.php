<?php

namespace App\Repositories\Eloquent;

use App\Enums\MembershipStatus;
use App\Models\User;
use App\Repositories\Contracts\MemberRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

class MemberRepository implements MemberRepositoryInterface
{
    private const RELATIONS = ['membership', 'organisationProfile'];

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->orderByDesc('users.created_at')
            ->orderByDesc('users.uuid')
            ->paginate($perPage);
    }

    public function paginatePending(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filtered(['status' => MembershipStatus::PendingApproval->value] + $filters)
            ->whereNotNull('users.email_verified_at')
            ->orderBy('memberships.created_at')
            ->orderBy('users.uuid')
            ->paginate($perPage);
    }

    public function lazyForExport(array $filters): LazyCollection
    {
        return $this->filtered($filters)
            ->orderByDesc('users.created_at')
            ->orderByDesc('users.uuid')
            ->lazy(500);
    }

    public function findWithProfile(string $uuid): User
    {
        return User::with(self::RELATIONS)->whereHas('membership')->findOrFail($uuid);
    }

    private function filtered(array $filters): Builder
    {
        return User::query()
            ->select('users.*')
            ->join('memberships', 'memberships.user_uuid', '=', 'users.uuid')
            ->leftJoin('organisation_profiles', 'organisation_profiles.user_uuid', '=', 'users.uuid')
            ->with(self::RELATIONS)
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $term = '%'.addcslashes($search, '\%_').'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('users.name', 'like', $term)
                    ->orWhere('users.email', 'like', $term)
                    ->orWhere('organisation_profiles.name', 'like', $term)
                    ->orWhere('memberships.member_id', 'like', $term));
            })
            ->when($filters['member_type'] ?? null, fn (Builder $query, string $type) => $query->where('memberships.member_type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('memberships.status', $status))
            ->when($filters['joined_from'] ?? null, fn (Builder $query, string $date) => $query->where('memberships.created_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['joined_to'] ?? null, fn (Builder $query, string $date) => $query->where('memberships.created_at', '<=', Carbon::parse($date)->endOfDay()));
    }
}

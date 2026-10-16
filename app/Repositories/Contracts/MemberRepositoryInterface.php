<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

interface MemberRepositoryInterface
{
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    public function paginatePending(array $filters, int $perPage): LengthAwarePaginator;

    public function lazyForExport(array $filters): LazyCollection;

    public function findWithProfile(string $uuid): User;
}

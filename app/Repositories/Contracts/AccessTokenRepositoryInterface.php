<?php

namespace App\Repositories\Contracts;

use App\Models\PersonalAccessToken;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\NewAccessToken;

interface AccessTokenRepositoryInterface
{
    public function create(Model $subject, string $name, array $abilities, CarbonInterface $expiresAt): NewAccessToken;

    public function findForUpdate(string $uuid): ?PersonalAccessToken;

    public function deleteSession(Model $subject, string $sessionName): int;

    public function deleteClient(Model $subject, string $client): int;

    public function deleteAll(Model $subject): int;

    public function deleteAllExceptSession(Model $subject, string $sessionName): int;
}

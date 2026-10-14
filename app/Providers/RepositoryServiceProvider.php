<?php

namespace App\Providers;

use App\Repositories\Contracts\AccessTokenRepositoryInterface;
use App\Repositories\Contracts\AdminRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\AuthChallengeRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\AccessTokenRepository;
use App\Repositories\Eloquent\AdminRepository;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Repositories\Eloquent\AuthChallengeRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public array $bindings = [
        AuditLogRepositoryInterface::class => AuditLogRepository::class,
        AdminRepositoryInterface::class => AdminRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        AuthChallengeRepositoryInterface::class => AuthChallengeRepository::class,
        AccessTokenRepositoryInterface::class => AccessTokenRepository::class,
    ];
}

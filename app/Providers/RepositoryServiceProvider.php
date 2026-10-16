<?php

namespace App\Providers;

use App\Repositories\Contracts\AccessTokenRepositoryInterface;
use App\Repositories\Contracts\AdminRepositoryInterface;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use App\Repositories\Contracts\ArticleRevisionRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\AuthChallengeRepositoryInterface;
use App\Repositories\Contracts\EventRegistrationRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\EventSpeakerRepositoryInterface;
use App\Repositories\Contracts\MemberIdSequenceRepositoryInterface;
use App\Repositories\Contracts\MemberRepositoryInterface;
use App\Repositories\Contracts\MembershipRepositoryInterface;
use App\Repositories\Contracts\OrganisationProfileRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\AccessTokenRepository;
use App\Repositories\Eloquent\AdminRepository;
use App\Repositories\Eloquent\ArticleRepository;
use App\Repositories\Eloquent\ArticleRevisionRepository;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Repositories\Eloquent\AuthChallengeRepository;
use App\Repositories\Eloquent\EventRegistrationRepository;
use App\Repositories\Eloquent\EventRepository;
use App\Repositories\Eloquent\EventSpeakerRepository;
use App\Repositories\Eloquent\MemberIdSequenceRepository;
use App\Repositories\Eloquent\MemberRepository;
use App\Repositories\Eloquent\MembershipRepository;
use App\Repositories\Eloquent\OrganisationProfileRepository;
use App\Repositories\Eloquent\RoleRepository;
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
        MembershipRepositoryInterface::class => MembershipRepository::class,
        OrganisationProfileRepositoryInterface::class => OrganisationProfileRepository::class,
        MemberRepositoryInterface::class => MemberRepository::class,
        MemberIdSequenceRepositoryInterface::class => MemberIdSequenceRepository::class,
        EventRepositoryInterface::class => EventRepository::class,
        EventSpeakerRepositoryInterface::class => EventSpeakerRepository::class,
        EventRegistrationRepositoryInterface::class => EventRegistrationRepository::class,
        ArticleRepositoryInterface::class => ArticleRepository::class,
        ArticleRevisionRepositoryInterface::class => ArticleRevisionRepository::class,
        RoleRepositoryInterface::class => RoleRepository::class,
    ];
}

<?php

namespace App\Services\Dashboard;

use App\Enums\EventRegistrationStatus;
use App\Models\User;
use App\Repositories\Contracts\EventRegistrationRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Membership\MemberActivityService;

class MemberDashboardService
{
    private const UPCOMING_EVENTS = 5;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly EventRegistrationRepositoryInterface $registrations,
        private readonly MemberActivityService $activities,
    ) {}

    public function forMember(User $user): array
    {
        return [
            'member' => $this->users->withProfile($user),
            'upcoming_events' => $this->registrations->upcomingForMember($user->getKey(), EventRegistrationStatus::Registered, self::UPCOMING_EVENTS),
            'waitlisted_events' => $this->registrations->upcomingForMember($user->getKey(), EventRegistrationStatus::Waitlisted),
            'recent_activity' => $this->activities->latest($user->getKey()),
        ];
    }
}

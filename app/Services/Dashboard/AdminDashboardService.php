<?php

namespace App\Services\Dashboard;

use App\Enums\ArticleStatus;
use App\Enums\DashboardSection;
use App\Enums\ePermission;
use App\Models\Admin;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\EventRegistrationRepositoryInterface;
use App\Repositories\Contracts\MemberRepositoryInterface;
use App\Repositories\Contracts\MembershipRepositoryInterface;
use App\Services\Events\EventService;
use App\Services\Membership\AdminMemberService;
use Illuminate\Support\Carbon;

class AdminDashboardService
{
    private const UPCOMING_EVENTS = 5;

    private const AWAITING_REVIEW = 5;

    private const RECENT_ACTIVITY = 10;

    public function __construct(
        private readonly DashboardCache $cache,
        private readonly AdminMemberService $adminMembers,
        private readonly EventService $events,
        private readonly MemberRepositoryInterface $members,
        private readonly MembershipRepositoryInterface $memberships,
        private readonly EventRegistrationRepositoryInterface $registrations,
        private readonly ArticleRepositoryInterface $articles,
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    public function forAdmin(Admin $admin): array
    {
        $can = fn (ePermission $permission) => $admin->hasPermissionTo($permission->value);
        $sections = [];

        if ($can(ePermission::MembersView)) {
            $sections[DashboardSection::Members->value] = $this->cache->remember(DashboardSection::Members, fn () => $this->members());
        }

        if ($can(ePermission::EventsView) || $can(ePermission::EventRegistrationsView)) {
            $events = $this->cache->remember(DashboardSection::Events, fn () => $this->events());

            $sections[DashboardSection::Events->value] = array_filter([
                'upcoming' => $can(ePermission::EventsView) ? $events['upcoming'] : null,
                'registrations_this_week' => $can(ePermission::EventRegistrationsView) ? $events['registrations_this_week'] : null,
            ], fn (mixed $value) => $value !== null);
        }

        if ($can(ePermission::ArticlesView)) {
            $sections[DashboardSection::Articles->value] = $this->cache->remember(DashboardSection::Articles, fn () => $this->articles());
        }

        if ($can(ePermission::AuditLogsView)) {
            $sections[DashboardSection::RecentActivity->value] = $this->cache->remember(DashboardSection::RecentActivity, fn () => $this->auditLogs->latest(self::RECENT_ACTIVITY));
        }

        return $sections;
    }

    private function members(): array
    {
        return [
            'counts_by_status' => $this->adminMembers->overview(),
            'pending_approvals' => $this->members->countPending(),
            'new_registrations' => [
                'this_week' => $this->memberships->countCreatedSince($this->startOfWeek()),
                'this_month' => $this->memberships->countCreatedSince($this->startOfMonth()),
            ],
        ];
    }

    private function events(): array
    {
        return [
            'upcoming' => $this->events->upcoming(self::UPCOMING_EVENTS),
            'registrations_this_week' => $this->registrations->countRegisteredSince($this->startOfWeek()),
        ];
    }

    private function articles(): array
    {
        $counts = $this->articles->countsByStatus();
        $byStatus = array_map(fn (string $status) => $counts[$status] ?? 0, array_combine(ArticleStatus::values(), ArticleStatus::values()));

        return [
            'counts_by_status' => ['all' => array_sum($byStatus)] + $byStatus,
            'awaiting_review' => $this->articles->oldestInReview(self::AWAITING_REVIEW),
        ];
    }

    private function startOfWeek(): Carbon
    {
        return now(config('apfspn.reporting_timezone'))->startOfWeek(Carbon::MONDAY)->utc();
    }

    private function startOfMonth(): Carbon
    {
        return now(config('apfspn.reporting_timezone'))->startOfMonth()->utc();
    }
}

<?php

namespace App\Repositories\Eloquent;

use App\Enums\EventRegistrationStatus;
use App\Enums\MyEventsFilter;
use App\Models\EventRegistration;
use App\Repositories\Contracts\EventRegistrationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class EventRegistrationRepository implements EventRegistrationRepositoryInterface
{
    private const ATTENDEE_RELATIONS = ['user.membership', 'user.organisationProfile'];

    public function create(array $attributes): EventRegistration
    {
        return EventRegistration::create($attributes);
    }

    public function save(EventRegistration $registration): EventRegistration
    {
        $registration->save();

        return $registration;
    }

    public function findWithRelations(string $uuid): ?EventRegistration
    {
        return EventRegistration::with(['event', 'user'])->find($uuid);
    }

    public function findForUserForUpdate(string $eventUuid, string $userUuid): ?EventRegistration
    {
        return EventRegistration::where('event_uuid', $eventUuid)->where('user_uuid', $userUuid)->lockForUpdate()->first();
    }

    public function findForEventForUpdate(string $eventUuid, string $registrationUuid): ?EventRegistration
    {
        return EventRegistration::where('event_uuid', $eventUuid)->whereKey($registrationUuid)->lockForUpdate()->first();
    }

    public function countsByStatus(string $eventUuid): array
    {
        return $this->countsByStatusForEvents([$eventUuid])[$eventUuid] ?? [];
    }

    public function countsByStatusForEvents(array $eventUuids): array
    {
        if ($eventUuids === []) {
            return [];
        }

        $counts = [];

        EventRegistration::query()
            ->whereIn('event_uuid', $eventUuids)
            ->selectRaw('event_uuid, status, count(*) as total')
            ->groupBy('event_uuid', 'status')
            ->toBase()
            ->get()
            ->each(function (object $row) use (&$counts) {
                $counts[$row->event_uuid][$row->status] = (int) $row->total;
            });

        return $counts;
    }

    public function countActiveForUser(string $userUuid): int
    {
        return EventRegistration::where('user_uuid', $userUuid)->whereIn('status', EventRegistrationStatus::active())->count();
    }

    public function seatsTaken(string $eventUuid): int
    {
        return EventRegistration::where('event_uuid', $eventUuid)->whereIn('status', EventRegistrationStatus::seatHolding())->count();
    }

    public function nextWaitlistPosition(string $eventUuid): int
    {
        return (int) EventRegistration::where('event_uuid', $eventUuid)
            ->where('status', EventRegistrationStatus::Waitlisted)
            ->max('waitlist_position') + 1;
    }

    public function waitlistedForUpdate(string $eventUuid, ?int $limit): Collection
    {
        return EventRegistration::where('event_uuid', $eventUuid)
            ->where('status', EventRegistrationStatus::Waitlisted)
            ->orderBy('waitlist_position')
            ->when($limit, fn (Builder $query, int $limit) => $query->limit($limit))
            ->lockForUpdate()
            ->get();
    }

    public function shiftWaitlist(string $eventUuid, int $afterPosition, int $by): void
    {
        EventRegistration::where('event_uuid', $eventUuid)
            ->where('status', EventRegistrationStatus::Waitlisted)
            ->where('waitlist_position', '>', $afterPosition)
            ->decrement('waitlist_position', $by);
    }

    public function paginateAttendees(string $eventUuid, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->attendees($eventUuid, $filters)
            ->orderBy('event_registrations.registered_at')
            ->orderBy('event_registrations.uuid')
            ->paginate($perPage);
    }

    public function paginateWaitlist(string $eventUuid, int $perPage): LengthAwarePaginator
    {
        return EventRegistration::with(self::ATTENDEE_RELATIONS)
            ->where('event_uuid', $eventUuid)
            ->where('status', EventRegistrationStatus::Waitlisted)
            ->orderBy('waitlist_position')
            ->paginate($perPage);
    }

    public function lazyForExport(string $eventUuid, array $filters): LazyCollection
    {
        return $this->attendees($eventUuid, $filters)
            ->orderBy('event_registrations.registered_at')
            ->orderBy('event_registrations.uuid')
            ->lazy(500);
    }

    public function paginateForMember(string $userUuid, ?MyEventsFilter $filter, int $perPage): LengthAwarePaginator
    {
        $now = now();

        [$statuses, $ended, $direction] = match ($filter) {
            MyEventsFilter::Upcoming => [[EventRegistrationStatus::Registered, EventRegistrationStatus::Waitlisted], false, 'asc'],
            MyEventsFilter::Waitlisted => [[EventRegistrationStatus::Waitlisted], false, 'asc'],
            MyEventsFilter::Past => [EventRegistrationStatus::seatHolding(), true, 'desc'],
            MyEventsFilter::Attended => [[EventRegistrationStatus::Attended], null, 'desc'],
            null => [EventRegistrationStatus::active(), null, 'desc'],
        };

        return EventRegistration::query()
            ->select('event_registrations.*')
            ->join('events', 'events.uuid', '=', 'event_registrations.event_uuid')
            ->whereNull('events.deleted_at')
            ->with('event.speakers')
            ->where('event_registrations.user_uuid', $userUuid)
            ->whereIn('event_registrations.status', $statuses)
            ->when($ended === true, fn (Builder $query) => $query->where('events.ends_at', '<', $now))
            ->when($ended === false, fn (Builder $query) => $query->where('events.ends_at', '>=', $now))
            ->orderBy('events.starts_at', $direction)
            ->orderBy('event_registrations.uuid')
            ->paginate($perPage);
    }

    public function notifiableUuids(string $eventUuid): LazyCollection
    {
        return EventRegistration::where('event_uuid', $eventUuid)
            ->whereIn('status', [EventRegistrationStatus::Registered, EventRegistrationStatus::Waitlisted])
            ->whereHas('user')
            ->lazyById(500, 'uuid')
            ->map(fn (EventRegistration $registration) => $registration->getKey());
    }

    public function countRegisteredSince(Carbon $since): int
    {
        return EventRegistration::query()
            ->whereHas('event')
            ->whereIn('status', EventRegistrationStatus::active())
            ->where('registered_at', '>=', $since)
            ->count();
    }

    public function upcomingForMember(string $userUuid, EventRegistrationStatus $status, ?int $limit = null): Collection
    {
        return EventRegistration::query()
            ->select('event_registrations.*')
            ->join('events', 'events.uuid', '=', 'event_registrations.event_uuid')
            ->whereNull('events.deleted_at')
            ->with('event')
            ->where('event_registrations.user_uuid', $userUuid)
            ->where('event_registrations.status', $status)
            ->where('events.ends_at', '>=', now())
            ->orderBy('events.starts_at')
            ->orderBy('event_registrations.uuid')
            ->when($limit, fn (Builder $query, int $limit) => $query->limit($limit))
            ->get();
    }

    private function attendees(string $eventUuid, array $filters): Builder
    {
        return EventRegistration::query()
            ->select('event_registrations.*')
            ->join('users', 'users.uuid', '=', 'event_registrations.user_uuid')
            ->with(self::ATTENDEE_RELATIONS)
            ->where('event_registrations.event_uuid', $eventUuid)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('event_registrations.status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $term = '%'.addcslashes($search, '\%_').'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('users.name', 'like', $term)
                    ->orWhere('users.email', 'like', $term));
            });
    }
}

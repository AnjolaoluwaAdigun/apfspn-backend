<?php

namespace App\Services\Events;

use App\Enums\EventNotice;
use App\Enums\EventRegistrationStatus;
use App\Enums\EventStatus;
use App\Enums\MemberActivityType;
use App\Enums\MembershipStatus;
use App\Enums\MyEventsFilter;
use App\Exceptions\ApiException;
use App\Jobs\SendEventRegistrationEmail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Repositories\Contracts\EventRegistrationRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\MembershipRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\Membership\MemberActivityService;
use App\Support\CsvSanitizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class EventRegistrationService
{
    public const DEFAULT_PER_PAGE = 15;

    private const TRANSACTION_ATTEMPTS = 3;

    private const EXPORT_COLUMNS = [
        'name', 'email', 'phone', 'member_id', 'member_type', 'organisation', 'status',
        'waitlist_position', 'registered_at', 'attended_at',
    ];

    public function __construct(
        private readonly EventRepositoryInterface $events,
        private readonly EventRegistrationRepositoryInterface $registrations,
        private readonly AuditLogService $audit,
        private readonly MemberActivityService $activities,
        private readonly MembershipRepositoryInterface $memberships,
    ) {}

    public function register(User $user, string $eventUuid): EventRegistration
    {
        return DB::transaction(function () use ($user, $eventUuid) {
            $event = $this->lockedVisibleEvent($eventUuid);
            $this->assertActiveMember($user);
            $registration = $this->registrations->findForUserForUpdate($event->getKey(), $user->getKey());

            if ($registration?->status->isActive()) {
                return $registration->setRelation('event', $event);
            }

            $this->assertRegistrationOpen($event);

            $hasSeat = $event->capacity === null || $this->registrations->seatsTakenForUpdate($event->getKey()) < $event->capacity;
            $attributes = [
                'status' => $hasSeat ? EventRegistrationStatus::Registered : EventRegistrationStatus::Waitlisted,
                'waitlist_position' => $hasSeat ? null : $this->registrations->nextWaitlistPositionForUpdate($event->getKey()),
                'registered_at' => now(),
                'promoted_at' => null,
                'cancelled_at' => null,
                'attended_at' => null,
            ];

            if ($registration) {
                $registration->fill($attributes);
                $this->registrations->save($registration);
            } else {
                $registration = $this->registrations->create($attributes + [
                    'event_uuid' => $event->getKey(),
                    'user_uuid' => $user->getKey(),
                ]);
            }

            $this->activities->record($user->getKey(), $hasSeat ? MemberActivityType::EventRegistered : MemberActivityType::EventWaitlisted, $event);
            $this->notify($registration, $hasSeat ? EventNotice::Confirmed : EventNotice::Waitlisted);

            return $registration->setRelation('event', $event);
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function cancel(User $user, string $eventUuid): EventRegistration
    {
        return DB::transaction(function () use ($user, $eventUuid) {
            $event = $this->lockedVisibleEvent($eventUuid);
            $registration = $this->registrations->findForUserForUpdate($event->getKey(), $user->getKey());

            if (! $registration?->status->isActive()) {
                throw new ApiException('You are not registered for this event.', 404, ['code' => 'registration_not_found']);
            }

            if ($event->hasStarted()) {
                throw new ApiException('This event has already started.', 422, ['code' => 'event_started']);
            }

            $heldSeat = $registration->status->holdsSeat();
            $position = $registration->waitlist_position;

            $registration->fill([
                'status' => EventRegistrationStatus::Cancelled,
                'waitlist_position' => null,
                'cancelled_at' => now(),
            ]);
            $this->registrations->save($registration);
            $this->activities->record($user->getKey(), MemberActivityType::EventRegistrationCancelled, $event);

            if ($position !== null) {
                $this->registrations->shiftWaitlist($event->getKey(), $position, 1);
            }

            if ($heldSeat) {
                $this->promoteWaitlisted($event);
            }

            return $registration->setRelation('event', $event);
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function promoteWaitlisted(Event $event): int
    {
        if ($event->status !== EventStatus::Published || $event->hasStarted()) {
            return 0;
        }

        $limit = $event->capacity === null ? null : $event->capacity - $this->registrations->seatsTakenForUpdate($event->getKey());

        if ($limit !== null && $limit <= 0) {
            return 0;
        }

        $promoted = $this->registrations->waitlistedForUpdate($event->getKey(), $limit);

        foreach ($promoted as $registration) {
            $registration->fill([
                'status' => EventRegistrationStatus::Registered,
                'waitlist_position' => null,
                'promoted_at' => now(),
            ]);
            $this->registrations->save($registration);
            $this->activities->record($registration->user_uuid, MemberActivityType::EventPromoted, $event);
            $this->notify($registration, EventNotice::Promoted);
        }

        if ($promoted->isNotEmpty()) {
            $this->registrations->shiftWaitlist($event->getKey(), 0, $promoted->count());
        }

        return $promoted->count();
    }

    public function markAttended(string $eventUuid, string $registrationUuid): EventRegistration
    {
        return DB::transaction(function () use ($eventUuid, $registrationUuid) {
            $event = $this->events->findForUpdate($eventUuid)
                ?? throw (new ModelNotFoundException)->setModel(Event::class, [$eventUuid]);
            $registration = $this->registrations->findForEventForUpdate($event->getKey(), $registrationUuid)
                ?? throw (new ModelNotFoundException)->setModel(EventRegistration::class, [$registrationUuid]);

            if ($registration->status === EventRegistrationStatus::Attended) {
                return $registration;
            }

            if ($registration->status !== EventRegistrationStatus::Registered) {
                throw new ApiException('Only registered attendees can be marked as attended.', 422, [
                    'code' => 'invalid_status_transition',
                    'current_status' => $registration->status->value,
                ]);
            }

            if (! in_array($event->status, EventStatus::publiclyVisible(), true)) {
                throw new ApiException('Attendance can only be recorded for published or completed events.', 422, [
                    'code' => 'invalid_status_transition',
                    'current_status' => $event->status->value,
                ]);
            }

            if (! $event->hasStarted()) {
                throw new ApiException('Attendance can be recorded once the event has started.', 422, ['code' => 'event_not_started']);
            }

            $registration->fill(['status' => EventRegistrationStatus::Attended, 'attended_at' => now()]);
            $changes = $this->audit->changeSet($registration);
            $this->registrations->save($registration);
            $this->activities->record($registration->user_uuid, MemberActivityType::EventAttended, $event);
            $this->audit->record('event_registration.attended', $registration, $changes);

            return $registration;
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function attendees(string $eventUuid, array $filters): LengthAwarePaginator
    {
        $this->events->findWithSpeakers($eventUuid);

        return $this->registrations->paginateAttendees($eventUuid, $filters, $this->perPage($filters));
    }

    public function waitlist(string $eventUuid, array $filters): LengthAwarePaginator
    {
        $this->events->findWithSpeakers($eventUuid);

        return $this->registrations->paginateWaitlist($eventUuid, $this->perPage($filters));
    }

    public function export(string $eventUuid, array $filters): iterable
    {
        $event = $this->events->findWithSpeakers($eventUuid);
        $this->audit->record('event_registrations.exported', $event);

        return $this->exportRows($eventUuid, $filters);
    }

    public function myEvents(User $user, array $filters): LengthAwarePaginator
    {
        $filter = isset($filters['filter']) ? MyEventsFilter::from($filters['filter']) : null;

        return $this->registrations->paginateForMember($user->getKey(), $filter, $this->perPage($filters));
    }

    private function exportRows(string $eventUuid, array $filters): iterable
    {
        yield self::EXPORT_COLUMNS;

        foreach ($this->registrations->lazyForExport($eventUuid, $filters) as $registration) {
            $user = $registration->user;

            yield CsvSanitizer::row([
                $user?->name,
                $user?->email,
                $user?->phone,
                $user?->membership?->member_id,
                $user?->membership?->member_type->value,
                $user?->organisationProfile?->name,
                $registration->status->value,
                $registration->waitlist_position,
                $registration->registered_at?->toIso8601String(),
                $registration->attended_at?->toIso8601String(),
            ]);
        }
    }

    private function assertActiveMember(User $user): void
    {
        $membership = $this->memberships->findByUser($user->getKey());

        if ($membership?->status !== MembershipStatus::Active) {
            throw new ApiException('Only active members can register for events.', 403, [
                'code' => 'membership_not_active',
                'membership_status' => $membership?->status->value,
            ]);
        }
    }

    private function lockedVisibleEvent(string $eventUuid): Event
    {
        $event = $this->events->findForUpdate($eventUuid);

        if (! $event || $event->status === EventStatus::Draft) {
            throw (new ModelNotFoundException)->setModel(Event::class, [$eventUuid]);
        }

        return $event;
    }

    private function assertRegistrationOpen(Event $event): void
    {
        if ($event->status === EventStatus::Cancelled) {
            throw new ApiException('This event has been cancelled.', 422, ['code' => 'event_cancelled']);
        }

        if ($event->status === EventStatus::Completed || $event->ends_at->isPast()) {
            throw new ApiException('This event has already taken place.', 422, ['code' => 'event_completed']);
        }

        if (! $event->registrationOpen()) {
            throw new ApiException('Registration for this event is closed.', 422, ['code' => 'registration_closed']);
        }
    }

    private function notify(EventRegistration $registration, EventNotice $notice): void
    {
        SendEventRegistrationEmail::dispatch(
            $registration->getKey(),
            $notice,
            (string) $registration->updated_at->getTimestamp(),
        )->afterCommit();
    }

    private function perPage(array $filters): int
    {
        return (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
    }
}

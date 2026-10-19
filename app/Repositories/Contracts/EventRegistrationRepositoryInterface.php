<?php

namespace App\Repositories\Contracts;

use App\Enums\EventRegistrationStatus;
use App\Enums\MyEventsFilter;
use App\Models\EventRegistration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

interface EventRegistrationRepositoryInterface
{
    public function create(array $attributes): EventRegistration;

    public function save(EventRegistration $registration): EventRegistration;

    public function findWithRelations(string $uuid): ?EventRegistration;

    public function findForUserForUpdate(string $eventUuid, string $userUuid): ?EventRegistration;

    public function findForEventForUpdate(string $eventUuid, string $registrationUuid): ?EventRegistration;

    public function countsByStatus(string $eventUuid): array;

    public function countsByStatusForEvents(array $eventUuids): array;

    public function countActiveForUser(string $userUuid): int;

    public function seatsTaken(string $eventUuid): int;

    public function nextWaitlistPosition(string $eventUuid): int;

    public function waitlistedForUpdate(string $eventUuid, ?int $limit): Collection;

    public function shiftWaitlist(string $eventUuid, int $afterPosition, int $by): void;

    public function paginateAttendees(string $eventUuid, array $filters, int $perPage): LengthAwarePaginator;

    public function paginateWaitlist(string $eventUuid, int $perPage): LengthAwarePaginator;

    public function lazyForExport(string $eventUuid, array $filters): LazyCollection;

    public function paginateForMember(string $userUuid, ?MyEventsFilter $filter, int $perPage): LengthAwarePaginator;

    public function notifiableUuids(string $eventUuid): LazyCollection;

    public function countRegisteredSince(Carbon $since): int;

    public function upcomingForMember(string $userUuid, EventRegistrationStatus $status, ?int $limit = null): Collection;
}

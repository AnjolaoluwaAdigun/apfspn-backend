<?php

namespace App\Services\Events;

use App\Enums\EventAudience;
use App\Enums\EventRegistrationStatus;
use App\Enums\EventStatus;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Jobs\NotifyEventCancelled;
use App\Models\Admin;
use App\Models\Event;
use App\Repositories\Contracts\EventRegistrationRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventService
{
    public const DEFAULT_PER_PAGE = 15;

    private const TRANSACTION_ATTEMPTS = 3;

    private const SLUG_MAX_LENGTH = 120;

    private const FIELDS = [
        'title', 'slug', 'description', 'starts_at', 'ends_at', 'venue', 'address', 'city', 'state_code',
        'is_virtual', 'virtual_link', 'audience', 'capacity', 'registration_closes_at', 'schedule',
    ];

    public function __construct(
        private readonly EventRepositoryInterface $events,
        private readonly EventRegistrationRepositoryInterface $registrations,
        private readonly EventSpeakerService $speakers,
        private readonly EventRegistrationService $eventRegistrations,
        private readonly AuditLogService $audit,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->withCounts($this->events->paginateAdmin($filters, $this->perPage($filters)));
    }

    public function show(string $uuid): Event
    {
        return $this->withCount($this->events->findWithSpeakers($uuid));
    }

    public function publicList(array $filters): LengthAwarePaginator
    {
        return $this->withCounts($this->events->paginatePublic($filters, $this->perPage($filters)));
    }

    public function publicShow(string $slug): Event
    {
        return $this->withCount($this->events->findPublicBySlug($slug));
    }

    public function create(array $data): Event
    {
        $imageUrl = FileUploadHelper::upload($data['image'] ?? null, config('apfspn.uploads.folders.event_images'));
        $speakers = $this->speakers->prepare($data['speakers'] ?? []);

        $uuid = DB::transaction(function () use ($data, $imageUrl, $speakers) {
            $adminUuid = $this->audit->currentAdminUuid();
            $event = new Event(Arr::only($data, self::FIELDS));

            $event->fill([
                'audience' => $data['audience'] ?? EventAudience::Public,
                'is_virtual' => $data['is_virtual'] ?? false,
                'slug' => $data['slug'] ?? $this->uniqueSlug($data['title']),
                'image_url' => $imageUrl,
                'status' => EventStatus::Draft,
                'created_by_admin_uuid' => $adminUuid,
                'updated_by_admin_uuid' => $adminUuid,
            ]);

            $this->assertConsistent($event);
            $this->events->save($event);
            $this->speakers->sync($event, $speakers);
            $this->audit->record('event.created', $event);

            return $event->getKey();
        }, self::TRANSACTION_ATTEMPTS);

        return $this->show($uuid);
    }

    public function update(string $uuid, array $data, Admin $admin): Event
    {
        $imageUrl = array_key_exists('image', $data)
            ? FileUploadHelper::upload($data['image'], config('apfspn.uploads.folders.event_images'))
            : null;
        $speakers = $this->speakers->prepare($data['speakers'] ?? null);

        DB::transaction(function () use ($uuid, $data, $admin, $imageUrl, $speakers) {
            $event = $this->lockedEvent($uuid);

            Gate::forUser($admin)->authorize('update', $event);

            if (! $event->status->isEditable()) {
                $this->refuseTransition($event, 'This event can no longer be edited.');
            }

            if (isset($data['slug']) && $data['slug'] !== $event->slug && $event->status !== EventStatus::Draft) {
                throw ValidationException::withMessages(['slug' => 'The slug cannot be changed after the event is published.']);
            }

            $event->fill(Arr::only($data, self::FIELDS));

            if ($imageUrl !== null) {
                $event->image_url = $imageUrl;
            }

            $this->assertConsistent($event);
            $this->assertCapacity($event);

            $changes = $this->audit->changeSet($event);

            if ($this->speakers->sync($event, $speakers)) {
                $changes['changed_fields'][] = 'speakers';
            }

            if ($changes['changed_fields'] === []) {
                return;
            }

            $event->updated_by_admin_uuid = $admin->getKey();
            $this->events->save($event);

            if ($event->wasChanged('capacity')) {
                $this->eventRegistrations->promoteWaitlisted($event);
            }

            $this->audit->record('event.updated', $event, $changes);
        }, self::TRANSACTION_ATTEMPTS);

        return $this->show($uuid);
    }

    public function delete(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $event = $this->lockedEvent($uuid);
            $counts = $this->statusCounts($event->getKey());

            if (array_sum($counts) > 0) {
                throw ApiException::blocked('This event has registrations and cannot be deleted. Cancel it instead.', $counts);
            }

            $this->audit->record('event.deleted', $event);
            $this->events->delete($event);
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function publish(string $uuid): Event
    {
        return $this->transition($uuid, function (Event $event) {
            if ($event->status === EventStatus::Published) {
                return false;
            }

            if (! $event->status->canBePublished()) {
                $this->refuseTransition($event, 'Only draft events can be published.');
            }

            if ($event->hasStarted()) {
                throw new ApiException('Events that have already started cannot be published.', 422, ['code' => 'event_in_past']);
            }

            $event->fill(['status' => EventStatus::Published, 'published_at' => now()]);

            return true;
        }, 'event.published');
    }

    public function cancel(string $uuid, string $reason): Event
    {
        return $this->transition($uuid, function (Event $event) use ($reason) {
            if ($event->status === EventStatus::Cancelled) {
                return false;
            }

            if (! $event->status->canBeCancelled()) {
                $this->refuseTransition($event, 'Only published events can be cancelled.');
            }

            $event->fill([
                'status' => EventStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            NotifyEventCancelled::dispatch($event->getKey())->afterCommit();

            return true;
        }, 'event.cancelled');
    }

    private function transition(string $uuid, Closure $apply, string $action): Event
    {
        DB::transaction(function () use ($uuid, $apply, $action) {
            $event = $this->lockedEvent($uuid);

            if (! $apply($event)) {
                return;
            }

            $event->updated_by_admin_uuid = $this->audit->currentAdminUuid() ?? $event->updated_by_admin_uuid;
            $changes = $this->audit->changeSet($event);
            $this->events->save($event);
            $this->audit->record($action, $event, $changes);
        }, self::TRANSACTION_ATTEMPTS);

        return $this->show($uuid);
    }

    private function assertConsistent(Event $event): void
    {
        $errors = [];

        if ($event->ends_at && $event->starts_at && $event->ends_at->lte($event->starts_at)) {
            $errors['ends_at'] = 'The end time must be after the start time.';
        }

        if ($event->registration_closes_at && $event->starts_at && $event->registration_closes_at->gt($event->starts_at)) {
            $errors['registration_closes_at'] = 'Registration must close before the event starts.';
        }

        if ($event->is_virtual && ! $event->virtual_link) {
            $errors['virtual_link'] = 'A virtual link is required for virtual events.';
        }

        if (! $event->is_virtual && ! $event->venue) {
            $errors['venue'] = 'A venue is required for in-person events.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assertCapacity(Event $event): void
    {
        if (! $event->isDirty('capacity') || $event->capacity === null) {
            return;
        }

        $taken = $this->registrations->seatsTaken($event->getKey());

        if ($event->capacity < $taken) {
            throw new ApiException('Capacity cannot be lower than the number of registered attendees.', 422, [
                'code' => 'capacity_below_registered',
                'registered_count' => $taken,
            ]);
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), self::SLUG_MAX_LENGTH - 6, '') ?: 'event';
        $slug = $base;
        $suffix = 2;

        while ($this->events->slugExists($slug)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function withCounts(LengthAwarePaginator $events): LengthAwarePaginator
    {
        $counts = $this->registrations->countsByStatusForEvents($events->getCollection()->modelKeys());

        $events->getCollection()->each(fn (Event $event) => $event->registrationCounts = $this->normaliseCounts($counts[$event->getKey()] ?? []));

        return $events;
    }

    private function withCount(Event $event): Event
    {
        $event->registrationCounts = $this->statusCounts($event->getKey());

        return $event;
    }

    private function statusCounts(string $eventUuid): array
    {
        return $this->normaliseCounts($this->registrations->countsByStatus($eventUuid));
    }

    private function normaliseCounts(array $counts): array
    {
        return array_map(
            fn (EventRegistrationStatus $status) => $counts[$status->value] ?? 0,
            array_combine(
                array_map(fn (EventRegistrationStatus $status) => $status->value, EventRegistrationStatus::active()),
                EventRegistrationStatus::active(),
            ),
        );
    }

    private function lockedEvent(string $uuid): Event
    {
        return $this->events->findForUpdate($uuid)
            ?? throw (new ModelNotFoundException)->setModel(Event::class, [$uuid]);
    }

    private function refuseTransition(Event $event, string $message): never
    {
        throw new ApiException($message, 422, [
            'code' => 'invalid_status_transition',
            'current_status' => $event->status->value,
        ]);
    }

    private function perPage(array $filters): int
    {
        return (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
    }
}

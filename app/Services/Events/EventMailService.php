<?php

namespace App\Services\Events;

use App\Enums\EventNotice;
use App\Jobs\SendEventRegistrationEmail;
use App\Mail\EventRegistrationMail;
use App\Repositories\Contracts\EventRegistrationRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use Illuminate\Support\Facades\Mail;

class EventMailService
{
    public function __construct(
        private readonly EventRepositoryInterface $events,
        private readonly EventRegistrationRepositoryInterface $registrations,
    ) {}

    public function send(string $registrationUuid, EventNotice $notice): void
    {
        $registration = $this->registrations->findWithRelations($registrationUuid);

        if (! $registration?->user || ! $registration->event) {
            return;
        }

        $event = $registration->event;
        $withLink = in_array($notice, [EventNotice::Confirmed, EventNotice::Promoted], true) && $event->is_virtual;

        Mail::to($registration->user->email)->send(new EventRegistrationMail(
            $notice,
            $registration->user->name,
            $event->title,
            $event->starts_at->toDayDateTimeString(),
            $event->is_virtual ? 'Online' : implode(', ', array_filter([$event->venue, $event->address, $event->city])),
            $withLink ? $event->virtual_link : null,
            $registration->waitlist_position,
            $notice === EventNotice::Cancelled ? $event->cancellation_reason : null,
        ));
    }

    public function notifyCancelled(string $eventUuid): int
    {
        $event = $this->events->findWithSpeakers($eventUuid);
        $occurredAt = (string) ($event->cancelled_at ?? now())->getTimestamp();
        $sent = 0;

        foreach ($this->registrations->notifiableUuids($eventUuid) as $registrationUuid) {
            SendEventRegistrationEmail::dispatch($registrationUuid, EventNotice::Cancelled, $occurredAt);
            $sent++;
        }

        return $sent;
    }
}

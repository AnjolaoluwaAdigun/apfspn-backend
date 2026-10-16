<?php

namespace Tests\Feature\Events;

use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concurrency\RunsConcurrentWorkers;
use Tests\Feature\AdminMembers\InteractsWithAdminMembers;
use Tests\TestCase;

#[Group('concurrency')]
class EventRegistrationConcurrencyTest extends TestCase
{
    use InteractsWithAdminMembers, RunsConcurrentWorkers;

    private const WORKER = 'tests/Concurrency/register-for-event.php';

    public function test_concurrent_registrations_never_overbook_the_last_seat(): void
    {
        try {
            $workerEnv = $this->prepareConcurrencyDatabase();

            $event = Event::factory()->published()->capacity(3)->create();
            EventRegistration::factory()->count(2)->for($event)->create();
            $members = array_map(fn () => $this->activeMember()->uuid, range(1, 6));

            $racers = [...$members, $members[0]];
            $results = $this->runWorkers(self::WORKER, array_map(fn (string $uuid) => [$event->uuid, $uuid], $racers), $workerEnv);

            foreach ($results as $result) {
                $this->assertTrue($result['ok'] ?? false, 'Worker failed: '.json_encode($result));
            }

            $registrations = EventRegistration::where('event_uuid', $event->uuid)->whereIn('user_uuid', $members)->get();

            $this->assertCount(6, $registrations);
            $this->assertCount(6, $registrations->pluck('user_uuid')->unique());
            $this->assertSame(3, EventRegistration::where('event_uuid', $event->uuid)->where('status', EventRegistrationStatus::Registered)->count());

            $waitlisted = $registrations->where('status', EventRegistrationStatus::Waitlisted);
            $this->assertCount(5, $waitlisted);
            $this->assertEqualsCanonicalizing([1, 2, 3, 4, 5], $waitlisted->pluck('waitlist_position')->all());

            $duplicates = array_values(array_filter($results, fn (array $result) => $result['member_uuid'] === $members[0]));
            $this->assertCount(2, $duplicates);
            $this->assertSame($duplicates[0]['registration_uuid'], $duplicates[1]['registration_uuid']);
            $this->assertSame($duplicates[0]['status'], $duplicates[1]['status']);
        } finally {
            $this->cleanUpWorkers();
        }
    }
}

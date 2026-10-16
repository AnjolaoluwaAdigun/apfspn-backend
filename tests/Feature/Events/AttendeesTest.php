<?php

namespace Tests\Feature\Events;

use App\Enums\EventRegistrationStatus;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\AdminMembers\InteractsWithAdminMembers;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class AttendeesTest extends TestCase
{
    use InteractsWithAdminMembers, InteractsWithAuth, InteractsWithEvents, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_attendee_list_filters_by_status_and_search(): void
    {
        $event = Event::factory()->published()->capacity(1)->create();
        EventRegistration::factory()->for($event)->for($this->activeMember(['name' => 'Ada Obi']))->create();
        EventRegistration::factory()->for($event)->for($this->activeMember(['name' => 'Bola Ade']))->waitlisted(1)->create();
        $token = $this->adminToken();

        $this->api('GET', $this->eventUrl($event, 'attendees'), [], $token)
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.pagination.total', 2);

        $this->api('GET', $this->eventUrl($event, 'attendees').'?status=registered', [], $token)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.member.name', 'Ada Obi')
            ->assertJsonPath('data.items.0.status', 'registered')
            ->assertJsonMissingPath('data.items.0.event');

        $this->api('GET', $this->eventUrl($event, 'attendees').'?search=bola', [], $token)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.member.name', 'Bola Ade');
    }

    public function test_waitlist_is_returned_in_order(): void
    {
        $event = Event::factory()->published()->capacity(1)->create();
        EventRegistration::factory()->for($event)->create();
        $third = EventRegistration::factory()->for($event)->waitlisted(3)->create();
        $first = EventRegistration::factory()->for($event)->waitlisted(1)->create();
        $second = EventRegistration::factory()->for($event)->waitlisted(2)->create();

        $items = $this->api('GET', $this->eventUrl($event, 'waitlist'), [], $this->adminToken())
            ->assertOk()
            ->json('data.items');

        $this->assertSame([$first->uuid, $second->uuid, $third->uuid], array_column($items, 'uuid'));
        $this->assertSame([1, 2, 3], array_column($items, 'waitlist_position'));
    }

    public function test_mark_attended_after_the_event_starts_and_is_idempotent(): void
    {
        $event = Event::factory()->published()->started()->create();
        $registration = EventRegistration::factory()->for($event)->create();
        $url = $this->eventUrl($event, 'attendees/'.$registration->uuid.'/attend');
        $token = $this->adminToken();

        $this->api('POST', $url, [], $token)
            ->assertOk()
            ->assertJsonPath('message', 'Attendance recorded.')
            ->assertJsonPath('data.status', 'attended');

        $attendedAt = $registration->fresh()->attended_at;
        $this->assertNotNull($attendedAt);

        $this->api('POST', $url, [], $token)->assertOk()->assertJsonPath('data.status', 'attended');

        $this->assertEquals($attendedAt, $registration->fresh()->attended_at);
        $this->assertSame(1, AuditLog::where('action', 'event_registration.attended')->count());
    }

    public function test_mark_attended_is_refused_before_the_event_or_for_waitlisted_members(): void
    {
        $token = $this->adminToken();
        $upcoming = Event::factory()->published()->create();
        $registration = EventRegistration::factory()->for($upcoming)->create();

        $this->api('POST', $this->eventUrl($upcoming, 'attendees/'.$registration->uuid.'/attend'), [], $token)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'event_not_started');

        $started = Event::factory()->published()->started()->capacity(1)->create();
        $waitlisted = EventRegistration::factory()->for($started)->waitlisted(1)->create();

        $this->api('POST', $this->eventUrl($started, 'attendees/'.$waitlisted->uuid.'/attend'), [], $token)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_status_transition')
            ->assertJsonPath('data.current_status', 'waitlisted');

        $this->api('POST', $this->eventUrl($started, 'attendees/'.$registration->uuid.'/attend'), [], $token)
            ->assertNotFound();

        $this->assertSame(EventRegistrationStatus::Registered, $registration->fresh()->status);
    }

    public function test_export_streams_csv_with_formula_guard(): void
    {
        $event = Event::factory()->published()->create(['slug' => 'chefs-connect']);
        $member = $this->activeMember(['name' => '=HYPERLINK("x")', 'phone' => '+2348031234567']);
        EventRegistration::factory()->for($event)->for($member)->create();
        EventRegistration::factory()->for($event)->for(User::factory())->cancelled()->create();

        $response = $this->api('GET', $this->eventUrl($event, 'attendees/export').'?status=registered', [], $this->adminToken())
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('apfspn-event-chefs-connect-attendees-', $response->headers->get('Content-Disposition'));

        $rows = array_map(str_getcsv(...), array_filter(explode("\n", $response->streamedContent())));

        $this->assertSame(['name', 'email', 'phone', 'member_id', 'member_type', 'organisation', 'status', 'waitlist_position', 'registered_at', 'attended_at'], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame("'=HYPERLINK(\"x\")", $rows[1][0]);
        $this->assertSame("'+2348031234567", $rows[1][2]);
        $this->assertSame($member->membership->member_id, $rows[1][3]);
        $this->assertSame('registered', $rows[1][6]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'event_registrations.exported', 'auditable_uuid' => $event->uuid]);
    }

    public function test_registration_permissions_by_role(): void
    {
        $event = Event::factory()->published()->started()->create();
        $registration = EventRegistration::factory()->for($event)->create();

        $editor = $this->adminToken('editor');
        $this->api('GET', $this->eventUrl($event, 'attendees'), [], $editor)->assertForbidden();
        $this->api('GET', $this->eventUrl($event, 'attendees/export'), [], $editor)->assertForbidden();

        $manager = $this->adminToken('association_manager');
        $this->api('GET', $this->eventUrl($event, 'attendees'), [], $manager)->assertOk();
        $this->api('GET', $this->eventUrl($event, 'waitlist'), [], $manager)->assertOk();
        $this->api('GET', $this->eventUrl($event, 'attendees/export'), [], $manager)->assertForbidden();
        $this->api('POST', $this->eventUrl($event, 'attendees/'.$registration->uuid.'/attend'), [], $manager)->assertForbidden();

        $coordinator = $this->adminToken('events_coordinator');
        $this->api('GET', $this->eventUrl($event, 'attendees/export'), [], $coordinator)->assertOk();
        $this->api('POST', $this->eventUrl($event, 'attendees/'.$registration->uuid.'/attend'), [], $coordinator)->assertOk();
    }
}

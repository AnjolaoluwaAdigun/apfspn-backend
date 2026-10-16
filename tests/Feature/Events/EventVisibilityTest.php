<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventSpeaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\AdminMembers\InteractsWithAdminMembers;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class EventVisibilityTest extends TestCase
{
    use InteractsWithAdminMembers, InteractsWithAuth, InteractsWithEvents, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_public_list_shows_published_events_and_marks_members_only_ones(): void
    {
        Event::factory()->published()->virtual()->membersOnly()->create(['title' => 'Members Webinar']);
        Event::factory()->published()->create(['title' => 'Open Day']);
        Event::factory()->create(['title' => 'Secret Draft']);
        Event::factory()->cancelled()->create(['title' => 'Called Off']);
        Event::factory()->completed()->create(['title' => 'Last Year']);

        $response = $this->api('GET', '/api/v1/events')
            ->assertOk()
            ->assertJsonPath('error', false)
            ->assertJsonPath('data.pagination.total', 3);

        $items = collect($response->json('data.items'))->keyBy('title');
        $this->assertEqualsCanonicalizing(['Members Webinar', 'Open Day', 'Last Year'], $items->keys()->all());
        $this->assertTrue($items['Members Webinar']['members_only']);
        $this->assertTrue($items['Members Webinar']['is_virtual']);
        $this->assertFalse($items['Open Day']['members_only']);

        foreach ($items as $item) {
            $this->assertArrayNotHasKey('virtual_link', $item);
            $this->assertArrayNotHasKey('registered_count', $item);
            $this->assertArrayNotHasKey('created_by_admin_uuid', $item);
        }
    }

    public function test_upcoming_and_past_filters_and_search(): void
    {
        Event::factory()->published()->create(['title' => 'Later', 'starts_at' => now()->addDays(20), 'ends_at' => now()->addDays(20)->addHours(3)]);
        Event::factory()->published()->create(['title' => 'Sooner', 'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(3)]);
        Event::factory()->completed()->create(['title' => 'Bygone Brunch']);

        $this->assertSame(['Sooner', 'Later'], array_column($this->api('GET', '/api/v1/events?when=upcoming')->json('data.items'), 'title'));
        $this->assertSame(['Bygone Brunch'], array_column($this->api('GET', '/api/v1/events?when=past')->json('data.items'), 'title'));
        $this->assertSame(['Later'], array_column($this->api('GET', '/api/v1/events?search=late')->json('data.items'), 'title'));

        $this->api('GET', '/api/v1/events?when=tomorrow')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['when'], 'data');
    }

    public function test_public_detail_shows_speakers_seats_and_whether_registration_is_open(): void
    {
        $event = Event::factory()->published()->virtual()->capacity(2)->create(['slug' => 'chefs-connect']);
        EventSpeaker::create(['event_uuid' => $event->uuid, 'name' => 'Chef Tolu', 'sort_order' => 0]);
        EventRegistration::factory()->for($event)->create();

        $this->api('GET', '/api/v1/events/chefs-connect')
            ->assertOk()
            ->assertJsonPath('data.uuid', $event->uuid)
            ->assertJsonPath('data.speakers.0.name', 'Chef Tolu')
            ->assertJsonPath('data.seats_left', 1)
            ->assertJsonPath('data.registration_open', true)
            ->assertJsonMissingPath('data.virtual_link');

        $closed = Event::factory()->published()->create(['slug' => 'closed-event', 'registration_closes_at' => now()->subMinute()]);
        $this->api('GET', '/api/v1/events/'.$closed->slug)->assertOk()->assertJsonPath('data.registration_open', false);
    }

    public function test_unlimited_events_report_null_seats_left(): void
    {
        Event::factory()->published()->create(['slug' => 'open-house']);

        $this->api('GET', '/api/v1/events/open-house')
            ->assertOk()
            ->assertJsonPath('data.capacity', null)
            ->assertJsonPath('data.seats_left', null);
    }

    public function test_drafts_cancelled_and_deleted_events_are_hidden_publicly(): void
    {
        Event::factory()->create(['slug' => 'draft-event']);
        Event::factory()->cancelled()->create(['slug' => 'cancelled-event']);
        $deleted = Event::factory()->published()->create(['slug' => 'deleted-event']);
        $deleted->delete();

        foreach (['draft-event', 'cancelled-event', 'deleted-event', 'no-such-event'] as $slug) {
            $this->api('GET', '/api/v1/events/'.$slug)->assertNotFound()->assertJsonPath('error', true);
        }
    }

    public function test_virtual_link_is_never_public_even_with_a_member_token(): void
    {
        $member = $this->activeMember();
        $event = Event::factory()->published()->virtual()->create(['slug' => 'webinar']);
        EventRegistration::factory()->for($event)->for($member)->create();

        $this->api('GET', '/api/v1/events/webinar', [], $this->memberToken($member))
            ->assertOk()
            ->assertJsonMissingPath('data.virtual_link');
    }

    public function test_virtual_link_reaches_registered_members_but_not_waitlisted_ones(): void
    {
        $event = Event::factory()->published()->virtual()->capacity(1)->create();
        $registered = $this->activeMember();
        $waitlisted = $this->activeMember();

        $this->api('POST', $this->registerUrl($event), [], $this->memberToken($registered))
            ->assertJsonPath('data.virtual_link', 'https://zoom.us/j/1234567890');
        $this->api('POST', $this->registerUrl($event), [], $this->memberToken($waitlisted))
            ->assertJsonPath('data.status', 'waitlisted')
            ->assertJsonPath('data.virtual_link', null);

        $this->api('GET', '/api/v1/member/events', [], $this->memberToken($waitlisted))
            ->assertJsonPath('data.items.0.virtual_link', null);
    }
}

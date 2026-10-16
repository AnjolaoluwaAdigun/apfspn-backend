<?php

namespace Tests\Feature\Events;

use App\Enums\EventRegistrationStatus;
use App\Enums\EventStatus;
use App\Jobs\SendEventRegistrationEmail;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventSpeaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\AdminMembers\InteractsWithAdminMembers;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\Feature\Membership\InteractsWithRegistration;
use Tests\TestCase;

class AdminEventCrudTest extends TestCase
{
    use InteractsWithAdminMembers, InteractsWithAuth, InteractsWithEvents, InteractsWithRegistration, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->fakeUploads();
    }

    public function test_admin_creates_a_draft_event_with_speakers_and_image(): void
    {
        $response = $this->api('POST', '/api/v1/admin/events', $this->eventPayload([
            'image' => 'data:image/png;base64,'.self::PNG,
            'audience' => 'members_only',
            'speakers' => [
                ['name' => 'Chef Tolu Eros', 'title' => 'Executive Chef', 'bio' => 'Founder of Calabash.', 'photo' => 'data:image/png;base64,'.self::PNG],
                ['name' => 'Amaka Obi', 'title' => 'Hospitality Consultant'],
            ],
        ]), $this->adminToken())
            ->assertCreated()
            ->assertJsonPath('error', false)
            ->assertJsonPath('message', 'Event created.')
            ->assertJsonPath('data.slug', 'lagos-food-service-summit-2026')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.members_only', true)
            ->assertJsonPath('data.capacity', 100)
            ->assertJsonPath('data.seats_left', 100)
            ->assertJsonPath('data.registered_count', 0)
            ->assertJsonPath('data.schedule.1.title', 'Keynote')
            ->assertJsonPath('data.speakers.0.name', 'Chef Tolu Eros')
            ->assertJsonPath('data.speakers.1.sort_order', 1);

        $this->assertNotNull($response->json('data.image_url'));
        $this->assertNotNull($response->json('data.speakers.0.photo_url'));

        $event = Event::findOrFail($response->json('data.uuid'));
        $this->assertNotNull($event->created_by_admin_uuid);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.created', 'auditable_uuid' => $event->uuid]);
    }

    public function test_slug_collisions_get_a_numeric_suffix(): void
    {
        $token = $this->adminToken();

        $this->api('POST', '/api/v1/admin/events', $this->eventPayload(), $token)->assertCreated();
        $this->api('POST', '/api/v1/admin/events', $this->eventPayload(), $token)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'lagos-food-service-summit-2026-2');

        $this->api('POST', '/api/v1/admin/events', $this->eventPayload(['slug' => 'lagos-food-service-summit-2026']), $token)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug'], 'data');
    }

    public function test_create_validates_fields_and_prohibits_protected_ones(): void
    {
        $this->api('POST', '/api/v1/admin/events', [
            'title' => '',
            'starts_at' => 'not a date',
            'status' => 'published',
            'published_at' => now()->toIso8601String(),
            'created_by_admin_uuid' => fake()->uuid(),
            'price' => 500000,
            'capacity' => 0,
            'audience' => 'everyone',
            'speakers' => [['title' => 'No name']],
        ], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonPath('error', true)
            ->assertJsonValidationErrors(['title', 'description', 'starts_at', 'status', 'published_at', 'created_by_admin_uuid', 'price', 'capacity', 'audience', 'speakers.0.name'], 'data');
    }

    public function test_create_requires_a_venue_or_a_virtual_link_and_a_sensible_schedule(): void
    {
        $token = $this->adminToken();

        $this->api('POST', '/api/v1/admin/events', $this->eventPayload(['venue' => null]), $token)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['venue'], 'data');

        $this->api('POST', '/api/v1/admin/events', $this->eventPayload(['venue' => null, 'is_virtual' => true]), $token)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['virtual_link'], 'data');

        $this->api('POST', '/api/v1/admin/events', $this->eventPayload([
            'registration_closes_at' => now()->addDays(31)->toIso8601String(),
        ]), $token)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['registration_closes_at'], 'data');

        $this->api('POST', '/api/v1/admin/events', $this->eventPayload([
            'venue' => null,
            'is_virtual' => true,
            'virtual_link' => 'https://zoom.us/j/1234567890',
        ]), $token)->assertCreated();
    }

    public function test_update_syncs_speakers_in_one_request(): void
    {
        $event = Event::factory()->create();
        $keep = EventSpeaker::create(['event_uuid' => $event->uuid, 'name' => 'Chef Tolu', 'sort_order' => 0]);
        $drop = EventSpeaker::create(['event_uuid' => $event->uuid, 'name' => 'Old Speaker', 'sort_order' => 1]);
        $other = EventSpeaker::create(['event_uuid' => Event::factory()->create()->uuid, 'name' => 'Elsewhere', 'sort_order' => 0]);
        $token = $this->adminToken();

        $this->api('PUT', $this->eventUrl($event), [
            'speakers' => [['uuid' => $other->uuid, 'name' => 'Hijack']],
        ], $token)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['speakers.0.uuid'], 'data');

        $this->api('PUT', $this->eventUrl($event), [
            'title' => 'Renamed Summit',
            'speakers' => [
                ['name' => 'New Speaker', 'title' => 'Nutritionist'],
                ['uuid' => $keep->uuid, 'name' => 'Chef Tolu Eros'],
            ],
        ], $token)
            ->assertOk()
            ->assertJsonPath('message', 'Event updated.')
            ->assertJsonPath('data.title', 'Renamed Summit')
            ->assertJsonCount(2, 'data.speakers')
            ->assertJsonPath('data.speakers.0.name', 'New Speaker')
            ->assertJsonPath('data.speakers.1.uuid', $keep->uuid)
            ->assertJsonPath('data.speakers.1.name', 'Chef Tolu Eros');

        $this->assertDatabaseMissing('event_speakers', ['uuid' => $drop->uuid]);

        $audit = AuditLog::where('action', 'event.updated')->sole();
        $this->assertEqualsCanonicalizing(['title', 'speakers'], $audit->changes['changed_fields']);
    }

    public function test_noop_update_is_not_audited(): void
    {
        $event = Event::factory()->create(['title' => 'Same Title']);

        $this->api('PUT', $this->eventUrl($event), ['title' => 'Same Title'], $this->adminToken())->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'event.updated']);
    }

    public function test_update_checks_partial_dates_against_the_stored_event(): void
    {
        $event = Event::factory()->create();

        $this->api('PUT', $this->eventUrl($event), ['ends_at' => $event->starts_at->copy()->subHour()->toIso8601String()], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ends_at'], 'data');
    }

    public function test_capacity_cannot_drop_below_registered_seats(): void
    {
        $event = Event::factory()->published()->capacity(3)->create();
        EventRegistration::factory()->count(2)->for($event)->create();
        EventRegistration::factory()->for($event)->attended()->create();

        $this->api('PUT', $this->eventUrl($event), ['capacity' => 2], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'capacity_below_registered')
            ->assertJsonPath('data.registered_count', 3);

        $this->assertSame(3, $event->fresh()->capacity);
    }

    public function test_raising_capacity_promotes_the_waitlist_in_order(): void
    {
        $event = Event::factory()->published()->capacity(1)->create();
        EventRegistration::factory()->for($event)->create();
        $first = EventRegistration::factory()->for($event)->waitlisted(1)->create();
        $second = EventRegistration::factory()->for($event)->waitlisted(2)->create();
        $third = EventRegistration::factory()->for($event)->waitlisted(3)->create();

        $this->api('PUT', $this->eventUrl($event), ['capacity' => 3], $this->adminToken())
            ->assertOk()
            ->assertJsonPath('data.registered_count', 3)
            ->assertJsonPath('data.waitlisted_count', 1)
            ->assertJsonPath('data.seats_left', 0);

        $this->assertSame(EventRegistrationStatus::Registered, $first->fresh()->status);
        $this->assertSame(EventRegistrationStatus::Registered, $second->fresh()->status);
        $this->assertNotNull($second->fresh()->promoted_at);
        $this->assertSame(EventRegistrationStatus::Waitlisted, $third->fresh()->status);
        $this->assertSame(1, $third->fresh()->waitlist_position);

        Queue::assertPushed(SendEventRegistrationEmail::class, 2);
    }

    public function test_cancelled_and_completed_events_cannot_be_edited(): void
    {
        $token = $this->adminToken();

        foreach ([Event::factory()->cancelled()->create(), Event::factory()->completed()->create()] as $event) {
            $this->api('PUT', $this->eventUrl($event), ['title' => 'Too late'], $token)
                ->assertStatus(422)
                ->assertJsonPath('data.code', 'invalid_status_transition')
                ->assertJsonPath('data.current_status', $event->status->value);
        }
    }

    public function test_slug_is_locked_once_published(): void
    {
        $event = Event::factory()->published()->create();

        $this->api('PUT', $this->eventUrl($event), ['slug' => 'new-slug'], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug'], 'data');
    }

    public function test_delete_is_blocked_while_anyone_is_registered(): void
    {
        $event = Event::factory()->published()->capacity(1)->create();
        EventRegistration::factory()->for($event)->create();
        EventRegistration::factory()->for($event)->waitlisted(1)->create();
        EventRegistration::factory()->for($event)->cancelled()->create();

        $this->api('DELETE', $this->eventUrl($event), [], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'blocked')
            ->assertJsonPath('data.counts', ['registered' => 1, 'waitlisted' => 1, 'attended' => 0]);

        $this->assertNotSoftDeleted($event);
    }

    public function test_delete_soft_deletes_an_event_without_registrations(): void
    {
        $event = Event::factory()->create();
        EventRegistration::factory()->for($event)->cancelled()->create();
        $token = $this->adminToken();

        $this->api('DELETE', $this->eventUrl($event), [], $token)
            ->assertOk()
            ->assertJsonPath('message', 'Event deleted.')
            ->assertJsonPath('data', null);

        $this->assertSoftDeleted($event);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.deleted', 'auditable_uuid' => $event->uuid]);
        $this->api('GET', $this->eventUrl($event), [], $token)->assertNotFound();
    }

    public function test_list_filters_and_paginates(): void
    {
        Event::factory()->create(['title' => 'Draft Gathering']);
        Event::factory()->published()->membersOnly()->create(['title' => 'Members Mixer', 'starts_at' => '2026-12-05 10:00:00', 'ends_at' => '2026-12-05 14:00:00']);
        Event::factory()->published()->create(['title' => 'Open Day', 'starts_at' => '2027-01-10 10:00:00', 'ends_at' => '2027-01-10 14:00:00']);
        $token = $this->adminToken();

        $this->api('GET', '/api/v1/admin/events?per_page=2', [], $token)
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.last_page', 2);

        $this->api('GET', '/api/v1/admin/events?status=published&audience=members_only', [], $token)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Members Mixer');

        $this->api('GET', '/api/v1/admin/events?starts_from=2027-01-01&search=open', [], $token)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Open Day');

        $this->api('GET', '/api/v1/admin/events?status=archived', [], $token)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status'], 'data');
    }

    public function test_show_returns_counts_and_the_virtual_link(): void
    {
        $event = Event::factory()->published()->virtual()->capacity(5)->create();
        EventRegistration::factory()->count(2)->for($event)->create();
        EventRegistration::factory()->for($event)->attended()->create();

        $this->api('GET', $this->eventUrl($event), [], $this->adminToken())
            ->assertOk()
            ->assertJsonPath('data.virtual_link', 'https://zoom.us/j/1234567890')
            ->assertJsonPath('data.registered_count', 2)
            ->assertJsonPath('data.attended_count', 1)
            ->assertJsonPath('data.seats_left', 2);
    }

    public function test_unknown_or_malformed_event_ids_return_404(): void
    {
        $token = $this->adminToken();

        $this->api('GET', '/api/v1/admin/events/'.fake()->uuid(), [], $token)->assertNotFound()->assertJsonPath('error', true);
        $this->api('GET', '/api/v1/admin/events/not-a-uuid', [], $token)->assertNotFound();
    }

    #[DataProvider('rolePermissions')]
    public function test_roles_get_only_their_event_permissions(string $role, bool $view, bool $create, bool $delete, bool $publish, bool $cancel): void
    {
        $token = $this->adminToken($role);
        $draft = Event::factory()->create();
        $published = Event::factory()->published()->create();

        $this->api('GET', '/api/v1/admin/events', [], $token)->assertStatus($view ? 200 : 403);
        $this->api('POST', '/api/v1/admin/events', $this->eventPayload(), $token)->assertStatus($create ? 201 : 403);
        $this->api('POST', $this->eventUrl($draft, 'publish'), [], $token)->assertStatus($publish ? 200 : 403);
        $this->api('POST', $this->eventUrl($published, 'cancel'), ['reason' => 'Venue flooded.'], $token)->assertStatus($cancel ? 200 : 403);
        $this->api('DELETE', $this->eventUrl(Event::factory()->create()), [], $token)->assertStatus($delete ? 200 : 403);
    }

    public static function rolePermissions(): array
    {
        return [
            'super_admin' => ['super_admin', true, true, true, true, true],
            'events_coordinator' => ['events_coordinator', true, true, true, true, true],
            'editor' => ['editor', true, true, false, true, false],
            'author' => ['author', true, true, false, false, false],
            'association_manager' => ['association_manager', true, false, false, false, false],
            'membership_coordinator' => ['membership_coordinator', false, false, false, false, false],
        ];
    }

    public function test_publishing_and_editing_by_super_admin_sets_audit_stamps(): void
    {
        $event = Event::factory()->create();

        $this->api('PUT', $this->eventUrl($event), ['capacity' => 50], $this->adminToken())->assertOk();

        $this->assertNotNull($event->fresh()->updated_by_admin_uuid);
        $this->assertSame(EventStatus::Draft, $event->fresh()->status);
    }
}

<?php

namespace Tests\Feature\Events;

use App\Enums\EventNotice;
use App\Enums\EventRegistrationStatus;
use App\Enums\MembershipStatus;
use App\Jobs\SendEventRegistrationEmail;
use App\Mail\EventRegistrationMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Events\EventMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\AdminMembers\InteractsWithAdminMembers;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class EventRegistrationTest extends TestCase
{
    use InteractsWithAdminMembers, InteractsWithAuth, InteractsWithEvents, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_active_member_registers_and_gets_a_confirmation_email(): void
    {
        $member = $this->activeMember();
        $event = Event::factory()->published()->virtual()->capacity(10)->create();

        $response = $this->api('POST', $this->registerUrl($event), [], $this->memberToken($member))
            ->assertOk()
            ->assertJsonPath('message', 'You are registered for this event.')
            ->assertJsonPath('data.status', 'registered')
            ->assertJsonPath('data.waitlist_position', null)
            ->assertJsonPath('data.virtual_link', 'https://zoom.us/j/1234567890')
            ->assertJsonPath('data.event.uuid', $event->uuid);

        $this->assertArrayNotHasKey('virtual_link', $response->json('data.event'));

        Queue::assertPushed(SendEventRegistrationEmail::class, fn (SendEventRegistrationEmail $job) => $job->notice === EventNotice::Confirmed
            && $job->registrationUuid === $response->json('data.uuid'));

        Mail::fake();
        Queue::pushed(SendEventRegistrationEmail::class)->first()->handle(app(EventMailService::class));
        Mail::assertSent(EventRegistrationMail::class, fn (EventRegistrationMail $mail) => $mail->hasTo($member->email)
            && $mail->virtualLink === 'https://zoom.us/j/1234567890');
    }

    public function test_full_events_waitlist_members_in_order(): void
    {
        $event = Event::factory()->published()->capacity(1)->create();
        EventRegistration::factory()->for($event)->create();

        foreach ([1, 2] as $position) {
            $this->api('POST', $this->registerUrl($event), [], $this->memberToken($this->activeMember()))
                ->assertOk()
                ->assertJsonPath('message', 'The event is full. You have been added to the waitlist.')
                ->assertJsonPath('data.status', 'waitlisted')
                ->assertJsonPath('data.waitlist_position', $position)
                ->assertJsonPath('data.virtual_link', null);
        }

        Queue::assertPushed(SendEventRegistrationEmail::class, fn (SendEventRegistrationEmail $job) => $job->notice === EventNotice::Waitlisted);
    }

    public function test_registering_twice_is_safe(): void
    {
        $member = $this->activeMember();
        $event = Event::factory()->published()->create();
        $token = $this->memberToken($member);

        $first = $this->api('POST', $this->registerUrl($event), [], $token)->assertOk()->json('data.uuid');
        $this->api('POST', $this->registerUrl($event), [], $token)
            ->assertOk()
            ->assertJsonPath('data.uuid', $first)
            ->assertJsonPath('data.status', 'registered');

        $this->assertSame(1, EventRegistration::where('user_uuid', $member->uuid)->count());
        Queue::assertPushed(SendEventRegistrationEmail::class, 1);
    }

    public function test_cancelling_a_seat_promotes_the_first_waitlisted_member(): void
    {
        $member = $this->activeMember();
        $event = Event::factory()->published()->capacity(1)->create();
        $token = $this->memberToken($member);
        $this->api('POST', $this->registerUrl($event), [], $token)->assertOk();
        $first = EventRegistration::factory()->for($event)->waitlisted(1)->create();
        $second = EventRegistration::factory()->for($event)->waitlisted(2)->create();

        $this->api('DELETE', $this->registerUrl($event), [], $token)
            ->assertOk()
            ->assertJsonPath('message', 'Your registration has been cancelled.')
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(EventRegistrationStatus::Registered, $first->fresh()->status);
        $this->assertNull($first->fresh()->waitlist_position);
        $this->assertNotNull($first->fresh()->promoted_at);
        $this->assertSame(1, $second->fresh()->waitlist_position);

        Queue::assertPushed(SendEventRegistrationEmail::class, fn (SendEventRegistrationEmail $job) => $job->notice === EventNotice::Promoted
            && $job->registrationUuid === $first->uuid);
    }

    public function test_cancelling_a_waitlist_place_closes_the_gap_without_promoting(): void
    {
        $member = $this->activeMember();
        $event = Event::factory()->published()->capacity(1)->create();
        EventRegistration::factory()->for($event)->create();
        $ahead = EventRegistration::factory()->for($event)->waitlisted(1)->create();
        EventRegistration::factory()->for($event)->for($member)->waitlisted(2)->create();
        $behind = EventRegistration::factory()->for($event)->waitlisted(3)->create();

        $this->api('DELETE', $this->registerUrl($event), [], $this->memberToken($member))->assertOk();

        $this->assertSame(1, $ahead->fresh()->waitlist_position);
        $this->assertSame(EventRegistrationStatus::Waitlisted, $ahead->fresh()->status);
        $this->assertSame(2, $behind->fresh()->waitlist_position);
        Queue::assertNotPushed(SendEventRegistrationEmail::class, fn (SendEventRegistrationEmail $job) => $job->notice === EventNotice::Promoted);
    }

    public function test_re_registering_after_cancelling_reuses_the_row(): void
    {
        $member = $this->activeMember();
        $event = Event::factory()->published()->create();
        $token = $this->memberToken($member);

        $uuid = $this->api('POST', $this->registerUrl($event), [], $token)->json('data.uuid');
        $this->api('DELETE', $this->registerUrl($event), [], $token)->assertOk();

        $this->api('POST', $this->registerUrl($event), [], $token)
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonPath('data.status', 'registered')
            ->assertJsonPath('data.cancelled_at', null);
    }

    public function test_registration_is_refused_when_closed_cancelled_completed_or_started(): void
    {
        $token = $this->memberToken($this->activeMember());

        $cases = [
            'registration_closed' => Event::factory()->published()->create(['registration_closes_at' => now()->subHour()]),
            'event_cancelled' => Event::factory()->cancelled()->create(),
            'event_completed' => Event::factory()->completed()->create(),
        ];

        foreach ($cases as $code => $event) {
            $this->api('POST', $this->registerUrl($event), [], $token)
                ->assertStatus(422)
                ->assertJsonPath('data.code', $code);
        }

        $this->api('POST', $this->registerUrl(Event::factory()->published()->started()->create()), [], $token)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'registration_closed');

        $this->assertSame(0, EventRegistration::count());
    }

    public function test_drafts_deleted_and_unknown_events_return_404(): void
    {
        $token = $this->memberToken($this->activeMember());
        $deleted = Event::factory()->published()->create();
        $deleted->delete();

        $this->api('POST', $this->registerUrl(Event::factory()->create()), [], $token)->assertNotFound();
        $this->api('POST', $this->registerUrl($deleted), [], $token)->assertNotFound();
        $this->api('POST', '/api/v1/member/events/'.fake()->uuid().'/register', [], $token)->assertNotFound();
    }

    public function test_cancel_is_refused_after_the_event_starts_or_without_a_registration(): void
    {
        $member = $this->activeMember();
        $token = $this->memberToken($member);
        $started = Event::factory()->published()->started()->create();
        EventRegistration::factory()->for($started)->for($member)->create();

        $this->api('DELETE', $this->registerUrl($started), [], $token)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'event_started');

        $this->api('DELETE', $this->registerUrl(Event::factory()->published()->create()), [], $token)
            ->assertNotFound()
            ->assertJsonPath('data.code', 'registration_not_found');
    }

    public function test_pending_and_expired_members_cannot_register(): void
    {
        $event = Event::factory()->published()->create();

        $pending = $this->pendingMember();
        $this->api('POST', $this->registerUrl($event), [], $this->memberToken($pending))
            ->assertForbidden()
            ->assertJsonPath('data.code', 'membership_not_active')
            ->assertJsonPath('data.membership_status', 'pending_approval');

        $expired = $this->activeMember([], ['status' => MembershipStatus::Expired, 'expires_at' => now()->subDay()]);
        $this->api('POST', $this->registerUrl($event), [], $this->memberToken($expired))
            ->assertForbidden()
            ->assertJsonPath('data.code', 'membership_not_active')
            ->assertJsonPath('data.membership_status', 'expired');

        $this->assertSame(0, EventRegistration::count());
    }

    public function test_suspended_members_are_refused_at_the_token(): void
    {
        $member = $this->activeMember();
        $token = $this->memberToken($member);
        $member->membership->update(['status' => MembershipStatus::Suspended]);

        $this->api('POST', $this->registerUrl(Event::factory()->published()->create()), [], $token)
            ->assertForbidden()
            ->assertJsonPath('data.code', 'membership_suspended');
    }

    public function test_admin_tokens_and_guests_cannot_register(): void
    {
        $event = Event::factory()->published()->create();

        $this->api('POST', $this->registerUrl($event))->assertUnauthorized();
        $this->api('POST', $this->registerUrl($event), [], $this->adminToken())
            ->assertForbidden()
            ->assertJsonPath('data.code', 'wrong_audience');
    }

    public function test_clients_cannot_choose_the_registration_status_or_owner(): void
    {
        $event = Event::factory()->published()->create();

        $this->api('POST', $this->registerUrl($event), [
            'user_uuid' => fake()->uuid(),
            'status' => 'attended',
            'waitlist_position' => 1,
        ], $this->memberToken($this->activeMember()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_uuid', 'status', 'waitlist_position'], 'data');
    }

    public function test_members_can_register_for_members_only_events(): void
    {
        $event = Event::factory()->published()->membersOnly()->create();

        $this->api('POST', $this->registerUrl($event), [], $this->memberToken($this->activeMember()))
            ->assertOk()
            ->assertJsonPath('data.event.members_only', true);
    }

    public function test_my_events_filters(): void
    {
        $member = $this->activeMember();
        $upcoming = EventRegistration::factory()->for($member)->for(Event::factory()->published()->virtual())->create();
        $waitlisted = EventRegistration::factory()->for($member)->for(Event::factory()->published())->waitlisted(1)->create();
        $attended = EventRegistration::factory()->for($member)->for(Event::factory()->completed())->attended()->create();
        $missed = EventRegistration::factory()->for($member)->for(Event::factory()->completed())->create();
        EventRegistration::factory()->for($member)->for(Event::factory()->published())->cancelled()->create();
        EventRegistration::factory()->for(Event::factory()->published())->create();
        $token = $this->memberToken($member);

        $all = $this->api('GET', '/api/v1/member/events', [], $token)->assertOk()->json('data.items');
        $this->assertCount(4, $all);

        $this->api('GET', '/api/v1/member/events?filter=upcoming', [], $token)
            ->assertOk()
            ->assertJsonCount(2, 'data.items');

        $this->assertSame([$waitlisted->uuid], array_column($this->api('GET', '/api/v1/member/events?filter=waitlisted', [], $token)->json('data.items'), 'uuid'));
        $this->assertSame([$attended->uuid], array_column($this->api('GET', '/api/v1/member/events?filter=attended', [], $token)->json('data.items'), 'uuid'));
        $this->assertEqualsCanonicalizing([$attended->uuid, $missed->uuid], array_column($this->api('GET', '/api/v1/member/events?filter=past', [], $token)->json('data.items'), 'uuid'));

        $links = array_column($this->api('GET', '/api/v1/member/events?filter=upcoming', [], $token)->json('data.items'), 'virtual_link', 'uuid');
        $this->assertSame('https://zoom.us/j/1234567890', $links[$upcoming->uuid]);
        $this->assertNull($links[$waitlisted->uuid]);

        $this->api('GET', '/api/v1/member/events?filter=everything', [], $token)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filter'], 'data');
    }
}

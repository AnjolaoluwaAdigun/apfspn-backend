<?php

namespace Tests\Feature\Events;

use App\Enums\EventNotice;
use App\Enums\EventStatus;
use App\Jobs\NotifyEventCancelled;
use App\Jobs\SendEventRegistrationEmail;
use App\Mail\EventRegistrationMail;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Events\EventMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\AdminMembers\InteractsWithAdminMembers;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class EventPublishCancelTest extends TestCase
{
    use InteractsWithAdminMembers, InteractsWithAuth, InteractsWithEvents, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_publishing_a_draft_sets_published_at_and_audits(): void
    {
        $event = Event::factory()->create();

        $this->api('POST', $this->eventUrl($event, 'publish'), [], $this->adminToken())
            ->assertOk()
            ->assertJsonPath('message', 'Event published.')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.registration_open', true);

        $this->assertNotNull($event->fresh()->published_at);

        $audit = AuditLog::where('action', 'event.published')->sole();
        $this->assertSame('draft', $audit->changes['previous']['status']);
    }

    public function test_publishing_twice_is_a_noop(): void
    {
        $event = Event::factory()->published()->create(['published_at' => now()->subDay()]);
        $publishedAt = $event->published_at->toIso8601String();

        $this->api('POST', $this->eventUrl($event, 'publish'), [], $this->adminToken())
            ->assertOk()
            ->assertJsonPath('data.published_at', $publishedAt);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'event.published']);
    }

    public function test_events_that_already_started_cannot_be_published(): void
    {
        $event = Event::factory()->started()->create();

        $this->api('POST', $this->eventUrl($event, 'publish'), [], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'event_in_past');

        $this->assertSame(EventStatus::Draft, $event->fresh()->status);
    }

    public function test_cancelled_or_completed_events_cannot_be_published(): void
    {
        $token = $this->adminToken();

        foreach ([Event::factory()->cancelled()->create(), Event::factory()->completed()->create()] as $event) {
            $this->api('POST', $this->eventUrl($event, 'publish'), [], $token)
                ->assertStatus(422)
                ->assertJsonPath('data.code', 'invalid_status_transition')
                ->assertJsonPath('data.current_status', $event->status->value);
        }
    }

    public function test_cancel_requires_a_reason(): void
    {
        $event = Event::factory()->published()->create();

        $this->api('POST', $this->eventUrl($event, 'cancel'), [], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason'], 'data');
    }

    public function test_cancel_notifies_registered_and_waitlisted_members_only(): void
    {
        $event = Event::factory()->published()->capacity(1)->create();
        $registered = EventRegistration::factory()->for($event)->create();
        $waitlisted = EventRegistration::factory()->for($event)->waitlisted(1)->create();
        $cancelled = EventRegistration::factory()->for($event)->cancelled()->create();

        $this->api('POST', $this->eventUrl($event, 'cancel'), ['reason' => 'The venue is unavailable.'], $this->adminToken())
            ->assertOk()
            ->assertJsonPath('message', 'Event cancelled.')
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'The venue is unavailable.')
            ->assertJsonPath('data.registration_open', false);

        Queue::assertPushed(NotifyEventCancelled::class, fn (NotifyEventCancelled $job) => $job->eventUuid === $event->uuid);

        Mail::fake();
        Queue::pushed(NotifyEventCancelled::class)->first()->handle(app(EventMailService::class));

        $emailed = Queue::pushed(SendEventRegistrationEmail::class)->map(fn (SendEventRegistrationEmail $job) => $job->registrationUuid)->all();
        $this->assertEqualsCanonicalizing([$registered->uuid, $waitlisted->uuid], $emailed);
        $this->assertNotContains($cancelled->uuid, $emailed);

        Queue::pushed(SendEventRegistrationEmail::class)->first()->handle(app(EventMailService::class));

        Mail::assertSent(EventRegistrationMail::class, fn (EventRegistrationMail $mail) => $mail->notice === EventNotice::Cancelled
            && $mail->reason === 'The venue is unavailable.');
    }

    public function test_cancelling_twice_sends_nothing_new(): void
    {
        $event = Event::factory()->cancelled()->create();

        $this->api('POST', $this->eventUrl($event, 'cancel'), ['reason' => 'Again.'], $this->adminToken())
            ->assertOk()
            ->assertJsonPath('data.cancellation_reason', 'Venue unavailable.');

        Queue::assertNotPushed(NotifyEventCancelled::class);
    }

    public function test_drafts_cannot_be_cancelled(): void
    {
        $event = Event::factory()->create();

        $this->api('POST', $this->eventUrl($event, 'cancel'), ['reason' => 'Never mind.'], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_status_transition')
            ->assertJsonPath('data.current_status', 'draft');
    }

    public function test_editor_cannot_cancel_but_events_coordinator_can(): void
    {
        $event = Event::factory()->published()->create();

        $this->api('POST', $this->eventUrl($event, 'cancel'), ['reason' => 'Speaker unwell.'], $this->tokenFor($this->adminWithRole('editor')))
            ->assertForbidden();

        $this->api('POST', $this->eventUrl($event, 'cancel'), ['reason' => 'Speaker unwell.'], $this->tokenFor($this->adminWithRole('events_coordinator')))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }
}

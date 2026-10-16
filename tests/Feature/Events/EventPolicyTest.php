<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class EventPolicyTest extends TestCase
{
    use InteractsWithAuth, InteractsWithEvents, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_author_creates_and_updates_their_own_draft(): void
    {
        $author = $this->adminWithRole('author');
        $token = $this->tokenFor($author);

        $uuid = $this->api('POST', '/api/v1/admin/events', $this->eventPayload(), $token)
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.created_by_admin_uuid', $author->uuid)
            ->json('data.uuid');

        $this->api('PUT', '/api/v1/admin/events/'.$uuid, ['title' => 'Author Edit'], $token)
            ->assertOk()
            ->assertJsonPath('data.title', 'Author Edit');
    }

    public function test_author_cannot_update_another_authors_draft(): void
    {
        $owner = $this->adminWithRole('author');
        $event = Event::factory()->create(['created_by_admin_uuid' => $owner->uuid]);

        $this->api('PUT', $this->eventUrl($event), ['title' => 'Not mine'], $this->tokenFor($this->adminWithRole('author')))
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not allowed to perform this action.');
    }

    public function test_author_cannot_update_their_event_once_published(): void
    {
        $author = $this->adminWithRole('author');
        $event = Event::factory()->published()->create(['created_by_admin_uuid' => $author->uuid]);

        $this->api('PUT', $this->eventUrl($event), ['title' => 'Sneaky edit'], $this->tokenFor($author))->assertForbidden();

        $this->assertNotSame('Sneaky edit', $event->fresh()->title);
    }

    public function test_author_cannot_publish_or_cancel(): void
    {
        $author = $this->adminWithRole('author');
        $token = $this->tokenFor($author);
        $draft = Event::factory()->create(['created_by_admin_uuid' => $author->uuid]);

        $this->api('POST', $this->eventUrl($draft, 'publish'), [], $token)->assertForbidden();
        $this->api('POST', $this->eventUrl(Event::factory()->published()->create(), 'cancel'), ['reason' => 'Because.'], $token)->assertForbidden();

        $this->assertSame('draft', $draft->fresh()->status->value);
    }

    public function test_editor_can_update_any_published_event(): void
    {
        $event = Event::factory()->published()->create(['created_by_admin_uuid' => $this->adminWithRole('author')->uuid]);

        $this->api('PUT', $this->eventUrl($event), ['title' => 'Editor Edit'], $this->tokenFor($this->adminWithRole('editor')))
            ->assertOk()
            ->assertJsonPath('data.title', 'Editor Edit');
    }

    public function test_admins_without_events_update_get_403(): void
    {
        $event = Event::factory()->create();

        $this->api('PUT', $this->eventUrl($event), ['title' => 'Nope'], $this->tokenFor($this->adminWithRole('association_manager')))
            ->assertForbidden();
    }
}

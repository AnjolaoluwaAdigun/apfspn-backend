<?php

namespace Tests\Feature\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CompletePastEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_without_changing_anything(): void
    {
        $ended = Event::factory()->published()->past()->create();

        $this->artisan('events:complete-past', ['--dry-run' => true])
            ->expectsOutput('Would complete 1 events.')
            ->assertSuccessful();

        $this->assertSame(EventStatus::Published, $ended->fresh()->status);
    }

    public function test_completes_only_published_events_that_have_ended(): void
    {
        Log::spy();

        $ended = Event::factory()->published()->past()->create();
        $running = Event::factory()->published()->started()->create();
        $upcoming = Event::factory()->published()->create();
        $cancelled = Event::factory()->cancelled()->past()->create();
        $draft = Event::factory()->past()->create();

        $this->artisan('events:complete-past')
            ->expectsOutput('Completed 1 events.')
            ->assertSuccessful();

        $this->assertSame(EventStatus::Completed, $ended->fresh()->status);
        $this->assertSame(EventStatus::Published, $running->fresh()->status);
        $this->assertSame(EventStatus::Published, $upcoming->fresh()->status);
        $this->assertSame(EventStatus::Cancelled, $cancelled->fresh()->status);
        $this->assertSame(EventStatus::Draft, $draft->fresh()->status);

        Log::shouldHaveReceived('info')->with('events:complete-past', ['dry_run' => false, 'count' => 1]);

        $this->artisan('events:complete-past')->expectsOutput('Completed 0 events.')->assertSuccessful();
    }

    public function test_command_is_scheduled_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($scheduled) => str_contains($scheduled->command, 'events:complete-past'));

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame('0 * * * *', $event->expression);
    }
}

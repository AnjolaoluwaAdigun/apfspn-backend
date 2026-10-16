<?php

namespace Tests\Feature\Articles;

use App\Enums\ArticleNotice;
use App\Jobs\SendArticleNoticeEmail;
use App\Models\Admin;
use App\Models\Article;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublishScheduledArticlesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_dry_run_changes_nothing(): void
    {
        $due = Article::factory()->scheduled(now()->subMinute())->create();

        $this->artisan('articles:publish-scheduled', ['--dry-run' => true])
            ->expectsOutput('Would publish 1 articles.')
            ->assertSuccessful();

        $this->assertSame('scheduled', $due->fresh()->status->value);
        Queue::assertNothingPushed();
    }

    public function test_real_run_publishes_only_due_articles_and_emails_authors(): void
    {
        $author = Admin::factory()->create();
        $due = Article::factory()->scheduled(now()->subMinute())->create(['author_admin_uuid' => $author->uuid]);
        $exactlyNow = Article::factory()->scheduled(now())->create();
        $future = Article::factory()->scheduled(now()->addHour())->create();
        $draft = Article::factory()->create(['publish_at' => now()->subDay()]);

        $this->artisan('articles:publish-scheduled')
            ->expectsOutput('Published 2 articles.')
            ->assertSuccessful();

        $this->assertSame('published', $due->fresh()->status->value);
        $this->assertNotNull($due->fresh()->published_at);
        $this->assertSame('published', $exactlyNow->fresh()->status->value);
        $this->assertSame('scheduled', $future->fresh()->status->value);
        $this->assertSame('draft', $draft->fresh()->status->value);

        Queue::assertPushed(SendArticleNoticeEmail::class, fn (SendArticleNoticeEmail $job) => $job->articleUuid === $due->uuid && $job->notice === ArticleNotice::Published);

        $this->get('/api/v1/articles/'.$due->slug)->assertOk();

        $this->artisan('articles:publish-scheduled')->expectsOutput('Published 0 articles.')->assertSuccessful();
    }

    public function test_command_is_scheduled_every_minute_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'articles:publish-scheduled'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }
}

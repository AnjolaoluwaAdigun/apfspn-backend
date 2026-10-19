<?php

namespace Tests\Feature\Articles;

use App\Enums\ArticleNotice;
use App\Jobs\SendArticleNoticeEmail;
use App\Mail\ArticleNoticeMail;
use App\Models\Article;
use App\Models\AuditLog;
use App\Services\Articles\ArticleMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class ArticleWorkflowTest extends TestCase
{
    use InteractsWithArticles, InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_full_workflow_author_submits_editor_sends_back_author_fixes_editor_publishes(): void
    {
        $author = $this->adminWithRole('author');
        $editor = $this->adminWithRole('editor');
        $authorToken = $this->tokenFor($author);
        $editorToken = $this->tokenFor($editor);

        $uuid = $this->api('POST', '/api/v1/admin/articles', $this->articlePayload(), $authorToken)->assertCreated()->json('data.uuid');
        $article = Article::findOrFail($uuid);

        $this->api('POST', $this->articleUrl($article, 'publish'), [], $authorToken)->assertForbidden();

        $this->api('POST', $this->articleUrl($article, 'submit'), [], $authorToken)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');

        $this->api('PUT', $this->articleUrl($article), ['title' => 'Locked while in review'], $authorToken)->assertForbidden();

        $this->api('POST', $this->articleUrl($article, 'send-back'), [], $editorToken)
            ->assertUnprocessable()
            ->assertJsonStructure(['data' => ['review_note']]);

        $this->api('POST', $this->articleUrl($article, 'send-back'), ['review_note' => 'Please add a quote from the president.'], $editorToken)
            ->assertOk()
            ->assertJsonPath('data.status', 'changes_requested')
            ->assertJsonPath('data.review_note', 'Please add a quote from the president.')
            ->assertJsonPath('data.reviewer.uuid', $editor->uuid);

        Queue::assertPushed(SendArticleNoticeEmail::class, fn (SendArticleNoticeEmail $job) => $job->articleUuid === $uuid && $job->notice === ArticleNotice::SentBack);

        $this->api('PUT', $this->articleUrl($article), ['body' => '<p>"We are proud," said the president.</p>'], $authorToken)->assertOk();
        $this->api('POST', $this->articleUrl($article, 'submit'), [], $authorToken)->assertOk()->assertJsonPath('data.status', 'in_review');

        $this->api('POST', $this->articleUrl($article, 'publish'), [], $editorToken)
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.reviewer.uuid', $editor->uuid);

        $this->assertNotNull($article->fresh()->published_at);
        Queue::assertPushed(SendArticleNoticeEmail::class, fn (SendArticleNoticeEmail $job) => $job->notice === ArticleNotice::Published);
        $this->assertEqualsCanonicalizing(
            ['article.created', 'article.submitted', 'article.sent_back', 'article.updated', 'article.submitted', 'article.published'],
            AuditLog::where('action', 'like', 'article.%')->pluck('action')->all(),
        );
    }

    public function test_author_cannot_submit_someone_elses_draft(): void
    {
        $article = Article::factory()->create(['author_admin_uuid' => $this->adminWithRole('author')->uuid]);

        $this->api('POST', $this->articleUrl($article, 'submit'), [], $this->tokenFor($this->adminWithRole('author')))->assertForbidden();
        $this->assertSame('draft', $article->fresh()->status->value);
    }

    public function test_repeated_actions_return_200_unchanged_without_audit_or_email(): void
    {
        $editorToken = $this->tokenFor($this->adminWithRole('editor'));
        $published = Article::factory()->published()->create();
        $publishedAt = $published->published_at->toIso8601String();

        $this->api('POST', $this->articleUrl($published, 'publish'), [], $editorToken)
            ->assertOk()
            ->assertJsonPath('data.published_at', $publishedAt);

        $at = now()->addDays(3)->startOfMinute();
        $scheduled = Article::factory()->scheduled($at)->create();
        $this->api('POST', $this->articleUrl($scheduled, 'schedule'), ['publish_at' => $at->toIso8601String()], $editorToken)
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled');

        $author = $this->adminWithRole('author');
        $inReview = Article::factory()->inReview()->create(['author_admin_uuid' => $author->uuid]);
        $this->api('POST', $this->articleUrl($inReview, 'submit'), [], $this->tokenFor($author))->assertOk();

        $sentBack = Article::factory()->changesRequested()->create();
        $this->api('POST', $this->articleUrl($sentBack, 'send-back'), ['review_note' => 'Again'], $editorToken)
            ->assertOk()
            ->assertJsonPath('data.review_note', 'Please add a source.');

        $this->assertSame(0, AuditLog::where('action', 'like', 'article.%')->count());
        Queue::assertNothingPushed();
    }

    public function test_schedule_needs_a_future_time_and_can_be_rescheduled_and_unscheduled(): void
    {
        $token = $this->tokenFor($this->adminWithRole('editor'));
        $article = Article::factory()->inReview()->create();

        $this->api('POST', $this->articleUrl($article, 'schedule'), ['publish_at' => now()->subMinute()->toIso8601String()], $token)
            ->assertUnprocessable()
            ->assertJsonStructure(['data' => ['publish_at']]);

        $this->api('POST', $this->articleUrl($article, 'schedule'), ['publish_at' => now()->addDay()->toIso8601String()], $token)
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled');

        $later = now()->addDays(2)->startOfMinute();
        $this->api('POST', $this->articleUrl($article, 'schedule'), ['publish_at' => $later->toIso8601String()], $token)
            ->assertOk()
            ->assertJsonPath('data.publish_at', $later->toIso8601String());

        $this->api('GET', '/api/v1/articles/'.$article->slug)->assertNotFound();

        $this->api('POST', $this->articleUrl($article, 'unschedule'), [], $token)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review')
            ->assertJsonPath('data.publish_at', null);

        $this->api('POST', $this->articleUrl($article, 'unschedule'), [], $token)->assertOk()->assertJsonPath('data.status', 'in_review');

        $this->api('POST', $this->articleUrl(Article::factory()->create(), 'unschedule'), [], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'invalid_status_transition');
    }

    public function test_send_back_from_scheduled_clears_the_schedule(): void
    {
        $article = Article::factory()->scheduled()->create();

        $this->api('POST', $this->articleUrl($article, 'send-back'), ['review_note' => 'Hold this one.'], $this->tokenFor($this->adminWithRole('editor')))
            ->assertOk()
            ->assertJsonPath('data.status', 'changes_requested')
            ->assertJsonPath('data.publish_at', null);
    }

    public function test_archive_and_unarchive(): void
    {
        $token = $this->tokenFor($this->adminWithRole('editor'));
        $article = Article::factory()->published()->create();
        $publishedAt = $article->published_at->toIso8601String();

        $this->api('POST', $this->articleUrl($article, 'archive'), [], $token)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
        $this->api('GET', '/api/v1/articles/'.$article->slug)->assertNotFound();

        $this->api('POST', $this->articleUrl($article, 'publish'), [], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'invalid_status_transition');

        $this->api('POST', $this->articleUrl($article, 'unarchive'), [], $token)
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.published_at', $publishedAt)
            ->assertJsonPath('data.archived_at', null);

        $this->api('POST', $this->articleUrl(Article::factory()->create(), 'archive'), [], $token)
            ->assertUnprocessable()
            ->assertJsonPath('data.current_status', 'draft');
    }

    public function test_invalid_transitions_are_refused(): void
    {
        $editorToken = $this->tokenFor($this->adminWithRole('editor'));

        $this->api('POST', $this->articleUrl(Article::factory()->create(), 'send-back'), ['review_note' => 'Not ready.'], $editorToken)
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'invalid_status_transition');

        $this->api('POST', $this->articleUrl(Article::factory()->published()->create(), 'schedule'), ['publish_at' => now()->addDay()->toIso8601String()], $editorToken)
            ->assertUnprocessable()
            ->assertJsonPath('data.current_status', 'published');

        $author = $this->adminWithRole('author');
        $this->api('POST', $this->articleUrl(Article::factory()->published()->create(['author_admin_uuid' => $author->uuid]), 'submit'), [], $this->tokenFor($author))
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'invalid_status_transition');
    }

    public function test_association_manager_publishes_but_cannot_edit(): void
    {
        $token = $this->tokenFor($this->adminWithRole('association_manager'));
        $article = Article::factory()->inReview()->create();

        $this->api('PUT', $this->articleUrl($article), ['title' => 'Edit'], $token)->assertForbidden();
        $this->api('POST', $this->articleUrl($article, 'publish'), [], $token)->assertOk()->assertJsonPath('data.status', 'published');
        $this->api('POST', $this->articleUrl($article, 'archive'), [], $token)->assertForbidden();
    }

    public function test_publishing_your_own_article_sends_no_email(): void
    {
        $editor = $this->adminWithRole('editor');
        $article = Article::factory()->create(['author_admin_uuid' => $editor->uuid]);

        $this->api('POST', $this->articleUrl($article, 'publish'), [], $this->tokenFor($editor))->assertOk();

        Queue::assertNotPushed(SendArticleNoticeEmail::class);
    }

    public function test_notice_emails_reach_the_author_with_the_review_note(): void
    {
        Mail::fake();
        $author = $this->adminWithRole('author');
        $article = Article::factory()->changesRequested()->create(['author_admin_uuid' => $author->uuid, 'title' => 'Food Safety Week']);

        app(ArticleMailService::class)->send($article->uuid, ArticleNotice::SentBack);

        Mail::assertSent(ArticleNoticeMail::class, fn (ArticleNoticeMail $mail) => $mail->hasTo($author->email)
            && $mail->reviewNote === 'Please add a source.'
            && $mail->envelope()->subject === 'Changes requested: Food Safety Week');

        $mail = new ArticleNoticeMail(ArticleNotice::Published, 'Ada', 'Food Safety Week', null, 'https://admin.test/articles/1');
        $this->assertStringContainsString('has been published', $mail->render());
    }
}

<?php

namespace Tests\Feature\Articles;

use App\Models\Article;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class AdminArticleCrudTest extends TestCase
{
    use InteractsWithArticles, InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_author_creates_a_draft_with_normalised_tags_slug_and_first_revision(): void
    {
        $author = $this->adminWithRole('author');

        $response = $this->api('POST', '/api/v1/admin/articles', $this->articlePayload(), $this->tokenFor($author))
            ->assertCreated()
            ->assertJsonPath('error', false)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.slug', 'apfspn-welcomes-new-members-in-lagos')
            ->assertJsonPath('data.tags', ['membership', 'lagos'])
            ->assertJsonPath('data.author.uuid', $author->uuid)
            ->assertJsonPath('data.author_admin_uuid', $author->uuid)
            ->assertJsonPath('data.created_by_admin_uuid', $author->uuid)
            ->assertJsonPath('data.revision_count', 1)
            ->assertJsonPath('data.view_count', 0);

        $this->assertDatabaseHas('audit_logs', ['action' => 'article.created', 'auditable_uuid' => $response->json('data.uuid')]);
    }

    public function test_duplicate_titles_get_numbered_slugs(): void
    {
        $token = $this->tokenFor($this->adminWithRole('editor'));

        $this->api('POST', '/api/v1/admin/articles', $this->articlePayload(), $token)->assertCreated();
        $this->api('POST', '/api/v1/admin/articles', $this->articlePayload(), $token)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'apfspn-welcomes-new-members-in-lagos-2');
    }

    public function test_excerpt_is_derived_from_the_body_when_blank(): void
    {
        $payload = $this->articlePayload(['excerpt' => null, 'body' => '<p>First &amp; <em>only</em> paragraph.</p>']);

        $this->api('POST', '/api/v1/admin/articles', $payload, $this->tokenFor($this->adminWithRole('editor')))
            ->assertCreated()
            ->assertJsonPath('data.excerpt', 'First & only paragraph.');
    }

    public function test_create_validates_and_prohibits_protected_fields(): void
    {
        $token = $this->tokenFor($this->adminWithRole('editor'));

        $this->api('POST', '/api/v1/admin/articles', ['title' => 'No body'], $token)
            ->assertUnprocessable()
            ->assertJsonPath('error', true)
            ->assertJsonStructure(['data' => ['body', 'category']]);

        $this->api('POST', '/api/v1/admin/articles', $this->articlePayload([
            'status' => 'published',
            'author_admin_uuid' => fake()->uuid(),
            'view_count' => 900,
            'publish_at' => now()->addDay()->toIso8601String(),
        ]), $token)
            ->assertUnprocessable()
            ->assertJsonStructure(['data' => ['status', 'author_admin_uuid', 'view_count', 'publish_at']]);

        $this->assertSame(0, Article::count());
    }

    public function test_author_updates_own_draft_but_not_others_or_submitted_ones(): void
    {
        $author = $this->adminWithRole('author');
        $token = $this->tokenFor($author);
        $own = Article::factory()->create(['author_admin_uuid' => $author->uuid]);

        $this->api('PUT', $this->articleUrl($own), ['title' => 'Updated title'], $token)
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title');

        $others = Article::factory()->create(['author_admin_uuid' => $this->adminWithRole('author')->uuid]);
        $this->api('PUT', $this->articleUrl($others), ['title' => 'Not mine'], $token)
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not allowed to perform this action.');

        $submitted = Article::factory()->inReview()->create(['author_admin_uuid' => $author->uuid]);
        $this->api('PUT', $this->articleUrl($submitted), ['title' => 'Sneaky'], $token)->assertForbidden();

        $sentBack = Article::factory()->changesRequested()->create(['author_admin_uuid' => $author->uuid]);
        $this->api('PUT', $this->articleUrl($sentBack), ['title' => 'Fixed'], $token)->assertOk();
    }

    public function test_editor_edits_a_published_article_live_and_slug_stays_locked(): void
    {
        $article = Article::factory()->published()->create();
        $token = $this->tokenFor($this->adminWithRole('editor'));

        $this->api('PUT', $this->articleUrl($article), ['body' => '<p>Corrected copy.</p>'], $token)
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.body', '<p>Corrected copy.</p>');

        $this->api('PUT', $this->articleUrl($article), ['slug' => 'a-new-slug'], $token)
            ->assertUnprocessable()
            ->assertJsonStructure(['data' => ['slug']]);

        $log = AuditLog::where('action', 'article.updated')->firstOrFail();
        $this->assertSame(['body'], $log->changes['changed_fields']);
        $this->assertArrayNotHasKey('body', $log->changes['previous']);
    }

    public function test_archived_articles_cannot_be_edited(): void
    {
        $article = Article::factory()->archived()->create();

        $this->api('PUT', $this->articleUrl($article), ['title' => 'Edit'], $this->tokenFor($this->adminWithRole('editor')))
            ->assertUnprocessable()
            ->assertJsonPath('data.code', 'invalid_status_transition')
            ->assertJsonPath('data.current_status', 'archived');
    }

    public function test_no_op_update_writes_no_audit_or_revision(): void
    {
        $author = $this->adminWithRole('author');
        $article = Article::factory()->create(['author_admin_uuid' => $author->uuid]);

        $this->api('PUT', $this->articleUrl($article), ['title' => $article->title], $this->tokenFor($author))->assertOk();

        $this->assertSame(0, AuditLog::where('action', 'article.updated')->count());
        $this->assertSame(0, $article->revisions()->count());
    }

    public function test_delete_allows_only_drafts_and_archived_articles(): void
    {
        $token = $this->tokenFor($this->adminWithRole('editor'));

        foreach (['inReview', 'scheduled', 'published', 'changesRequested'] as $state) {
            $this->api('DELETE', $this->articleUrl(Article::factory()->{$state}()->create()), [], $token)
                ->assertUnprocessable()
                ->assertJsonPath('data.code', 'invalid_status_transition');
        }

        $draft = Article::factory()->create();
        $archived = Article::factory()->archived()->create();

        $this->api('DELETE', $this->articleUrl($draft), [], $token)->assertOk();
        $this->api('DELETE', $this->articleUrl($archived), [], $token)->assertOk();

        $this->assertSoftDeleted($draft);
        $this->assertSoftDeleted($archived);
        $this->assertDatabaseHas('audit_logs', ['action' => 'article.deleted', 'auditable_uuid' => $draft->uuid]);
        $this->api('GET', $this->articleUrl($draft), [], $token)->assertNotFound();
    }

    public function test_slug_of_a_deleted_article_stays_reserved(): void
    {
        Article::factory()->create(['slug' => 'apfspn-welcomes-new-members-in-lagos'])->delete();

        $this->api('POST', '/api/v1/admin/articles', $this->articlePayload(), $this->tokenFor($this->adminWithRole('editor')))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'apfspn-welcomes-new-members-in-lagos-2');
    }

    public function test_list_filters_and_paginates_without_bodies(): void
    {
        $author = $this->adminWithRole('author');
        Article::factory()->count(2)->create(['author_admin_uuid' => $author->uuid]);
        Article::factory()->published()->create(['category' => 'press_release', 'title' => 'Annual Press Briefing']);
        Article::factory()->create(['created_at' => now()->subMonth()]);
        $token = $this->tokenFor($this->adminWithRole('author'));

        $this->api('GET', '/api/v1/admin/articles?per_page=2', [], $token)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 4)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonMissingPath('data.items.0.body');

        $this->api('GET', '/api/v1/admin/articles?author_uuid='.$author->uuid, [], $token)->assertJsonPath('data.pagination.total', 2);
        $this->api('GET', '/api/v1/admin/articles?status=published&category=press_release', [], $token)->assertJsonPath('data.pagination.total', 1);
        $this->api('GET', '/api/v1/admin/articles?search=Press%20Brief', [], $token)->assertJsonPath('data.items.0.title', 'Annual Press Briefing');
        $this->api('GET', '/api/v1/admin/articles?created_from='.now()->subDay()->toDateString(), [], $token)->assertJsonPath('data.pagination.total', 3);
        $this->api('GET', '/api/v1/admin/articles?status=live', [], $token)->assertUnprocessable();
    }

    public function test_show_returns_admin_fields_and_unknown_ids_are_404(): void
    {
        $article = Article::factory()->changesRequested()->create();
        $token = $this->tokenFor($this->adminWithRole('editor'));

        $this->api('GET', $this->articleUrl($article), [], $token)
            ->assertOk()
            ->assertJsonPath('data.status', 'changes_requested')
            ->assertJsonPath('data.review_note', 'Please add a source.')
            ->assertJsonPath('data.revision_count', 0);

        $this->api('GET', '/api/v1/admin/articles/'.fake()->uuid(), [], $token)->assertNotFound();
        $this->api('GET', '/api/v1/admin/articles/not-a-uuid', [], $token)->assertNotFound();
    }
}

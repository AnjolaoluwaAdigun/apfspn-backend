<?php

namespace Tests\Feature\Articles;

use App\Models\Article;
use App\Models\ArticleRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class ArticleRevisionTest extends TestCase
{
    use InteractsWithArticles, InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_content_saves_write_revisions_and_metadata_saves_do_not(): void
    {
        $author = $this->adminWithRole('author');
        $token = $this->tokenFor($author);
        $uuid = $this->api('POST', '/api/v1/admin/articles', $this->articlePayload(), $token)->json('data.uuid');
        $article = Article::findOrFail($uuid);

        $this->api('PUT', $this->articleUrl($article), ['title' => 'Second title'], $token)->assertOk();
        $this->api('PUT', $this->articleUrl($article), ['tags' => ['events']], $token)->assertOk();

        $this->api('GET', $this->articleUrl($article, 'revisions'), [], $token)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.items.0.title', 'Second title')
            ->assertJsonPath('data.items.0.edited_by.uuid', $author->uuid)
            ->assertJsonPath('data.items.1.title', 'APFSPN Welcomes New Members in Lagos');

        $this->api('GET', $this->articleUrl($article), [], $token)->assertJsonPath('data.revision_count', 2);
    }

    public function test_restore_copies_content_back_and_creates_a_new_revision(): void
    {
        $editor = $this->adminWithRole('editor');
        $token = $this->tokenFor($editor);
        $article = Article::factory()->create(['title' => 'Current', 'body' => '<p>Current body</p>']);
        $old = ArticleRevision::factory()->create([
            'article_uuid' => $article->uuid,
            'title' => 'Original',
            'excerpt' => 'Original excerpt',
            'body' => '<p>Original body</p>',
            'created_at' => now()->subDay(),
        ]);

        $this->api('POST', $this->articleUrl($article, 'revisions/'.$old->uuid.'/restore'), [], $token)
            ->assertOk()
            ->assertJsonPath('data.title', 'Original')
            ->assertJsonPath('data.body', '<p>Original body</p>')
            ->assertJsonPath('data.revision_count', 2);

        $latest = $article->revisions()->latest('created_at')->first();
        $this->assertNotSame($old->uuid, $latest->uuid);
        $this->assertSame('Original', $latest->title);
        $this->assertSame($editor->uuid, $latest->edited_by_admin_uuid);
        $this->assertDatabaseHas('audit_logs', ['action' => 'article.revision_restored', 'auditable_uuid' => $article->uuid]);

        $this->api('POST', $this->articleUrl($article, 'revisions/'.$old->uuid.'/restore'), [], $token)
            ->assertOk()
            ->assertJsonPath('data.revision_count', 2);
    }

    public function test_restore_follows_the_edit_rules(): void
    {
        $author = $this->adminWithRole('author');
        $article = Article::factory()->inReview()->create(['author_admin_uuid' => $author->uuid]);
        $revision = ArticleRevision::factory()->create(['article_uuid' => $article->uuid]);

        $this->api('POST', $this->articleUrl($article, 'revisions/'.$revision->uuid.'/restore'), [], $this->tokenFor($author))->assertForbidden();

        $other = ArticleRevision::factory()->create();
        $this->api('POST', $this->articleUrl($article, 'revisions/'.$other->uuid.'/restore'), [], $this->tokenFor($this->adminWithRole('editor')))->assertNotFound();
    }
}

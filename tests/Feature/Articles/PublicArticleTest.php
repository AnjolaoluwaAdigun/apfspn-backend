<?php

namespace Tests\Feature\Articles;

use App\Models\Admin;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class PublicArticleTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    public function test_listing_shows_only_published_articles_newest_first(): void
    {
        $older = Article::factory()->published()->create(['published_at' => now()->subDays(3)]);
        $newer = Article::factory()->published()->create(['published_at' => now()->subDay()]);
        Article::factory()->create();
        Article::factory()->inReview()->create();
        Article::factory()->changesRequested()->create();
        Article::factory()->scheduled()->create();
        Article::factory()->archived()->create();
        Article::factory()->published()->create()->delete();

        $this->api('GET', '/api/v1/articles')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.items.0.uuid', $newer->uuid)
            ->assertJsonPath('data.items.1.uuid', $older->uuid)
            ->assertJsonMissingPath('data.items.0.body')
            ->assertJsonMissingPath('data.items.0.status')
            ->assertJsonMissingPath('data.items.0.review_note');
    }

    public function test_listing_filters_by_category_and_search(): void
    {
        Article::factory()->published()->create(['category' => 'press_release', 'title' => 'Embargoed Briefing']);
        Article::factory()->published()->create(['category' => 'blog', 'title' => 'Kitchen Tips']);

        $this->api('GET', '/api/v1/articles?category=press_release')->assertJsonPath('data.pagination.total', 1);
        $this->api('GET', '/api/v1/articles?search=Kitchen')->assertJsonPath('data.items.0.title', 'Kitchen Tips');
        $this->api('GET', '/api/v1/articles?category=gossip')->assertUnprocessable();
    }

    public function test_detail_returns_published_article_and_counts_views(): void
    {
        $author = Admin::factory()->create(['name' => 'Ngozi Editor']);
        $article = Article::factory()->published()->create(['author_admin_uuid' => $author->uuid, 'review_note' => 'Internal']);
        $updatedAt = $article->updated_at->toIso8601String();

        $this->api('GET', '/api/v1/articles/'.$article->slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $article->slug)
            ->assertJsonPath('data.view_count', 1)
            ->assertJsonPath('data.author', ['name' => 'Ngozi Editor'])
            ->assertJsonMissingPath('data.review_note')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.author_admin_uuid');

        $this->api('GET', '/api/v1/articles/'.$article->slug)->assertJsonPath('data.view_count', 2);

        $this->assertSame(2, $article->fresh()->view_count);
        $this->assertSame($updatedAt, $article->fresh()->updated_at->toIso8601String());
    }

    public function test_unpublished_and_deleted_articles_are_404(): void
    {
        $hidden = [
            Article::factory()->create(),
            Article::factory()->inReview()->create(),
            Article::factory()->changesRequested()->create(),
            Article::factory()->scheduled()->create(),
            Article::factory()->archived()->create(),
            tap(Article::factory()->published()->create())->delete(),
        ];

        foreach ($hidden as $article) {
            $this->api('GET', '/api/v1/articles/'.$article->slug)->assertNotFound()->assertJsonPath('error', true);
            $this->assertSame(0, Article::withTrashed()->find($article->uuid)->view_count);
        }
    }
}

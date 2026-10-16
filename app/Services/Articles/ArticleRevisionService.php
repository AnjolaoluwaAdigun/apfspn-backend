<?php

namespace App\Services\Articles;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Repositories\Contracts\ArticleRevisionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ArticleRevisionService
{
    public const CONTENT_FIELDS = ['title', 'excerpt', 'body'];

    public function __construct(private readonly ArticleRevisionRepositoryInterface $revisions) {}

    public function record(Article $article, ?string $adminUuid): ArticleRevision
    {
        return $this->revisions->create([
            'article_uuid' => $article->getKey(),
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'edited_by_admin_uuid' => $adminUuid,
        ]);
    }

    public function contentChanged(Article $article): bool
    {
        return $article->wasRecentlyCreated || $article->wasChanged(self::CONTENT_FIELDS);
    }

    public function paginate(string $articleUuid, int $perPage): LengthAwarePaginator
    {
        return $this->revisions->paginateForArticle($articleUuid, $perPage);
    }

    public function find(string $articleUuid, string $revisionUuid): ArticleRevision
    {
        return $this->revisions->findForArticle($articleUuid, $revisionUuid);
    }
}

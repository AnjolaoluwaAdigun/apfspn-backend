<?php

namespace App\Repositories\Eloquent;

use App\Models\ArticleRevision;
use App\Repositories\Contracts\ArticleRevisionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ArticleRevisionRepository implements ArticleRevisionRepositoryInterface
{
    public function create(array $attributes): ArticleRevision
    {
        return ArticleRevision::create($attributes);
    }

    public function paginateForArticle(string $articleUuid, int $perPage): LengthAwarePaginator
    {
        return ArticleRevision::with('editor')
            ->where('article_uuid', $articleUuid)
            ->orderByDesc('created_at')
            ->orderByDesc('uuid')
            ->paginate($perPage);
    }

    public function findForArticle(string $articleUuid, string $revisionUuid): ArticleRevision
    {
        return ArticleRevision::where('article_uuid', $articleUuid)->findOrFail($revisionUuid);
    }
}

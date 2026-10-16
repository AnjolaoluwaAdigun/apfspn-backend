<?php

namespace App\Repositories\Contracts;

use App\Models\ArticleRevision;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ArticleRevisionRepositoryInterface
{
    public function create(array $attributes): ArticleRevision;

    public function paginateForArticle(string $articleUuid, int $perPage): LengthAwarePaginator;

    public function findForArticle(string $articleUuid, string $revisionUuid): ArticleRevision;
}

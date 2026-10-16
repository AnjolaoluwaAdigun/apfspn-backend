<?php

namespace App\Repositories\Contracts;

use App\Models\Article;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface ArticleRepositoryInterface
{
    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator;

    public function paginatePublic(array $filters, int $perPage): LengthAwarePaginator;

    public function findWithRelations(string $uuid): Article;

    public function findWithAuthor(string $uuid): ?Article;

    public function findPublicBySlug(string $slug): Article;

    public function findForUpdate(string $uuid): ?Article;

    public function slugExists(string $slug, ?string $ignoreUuid = null): bool;

    public function uuidsDueBefore(Carbon $moment): Collection;

    public function incrementViews(string $uuid): void;

    public function countAuthoredBy(string $adminUuid): int;

    public function save(Article $article): Article;

    public function delete(Article $article): void;
}

<?php

namespace App\Repositories\Eloquent;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ArticleRepository implements ArticleRepositoryInterface
{
    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->searched(Article::query(), $filters['search'] ?? null)
            ->with(['author', 'reviewer'])
            ->withCount('revisions')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['author_uuid'] ?? null, fn (Builder $query, string $uuid) => $query->where('author_admin_uuid', $uuid))
            ->when($filters['created_from'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['created_to'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '<=', Carbon::parse($date)->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('uuid')
            ->paginate($perPage);
    }

    public function paginatePublic(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->searched($this->publicQuery(), $filters['search'] ?? null)
            ->with('author')
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->orderByDesc('published_at')
            ->orderByDesc('uuid')
            ->paginate($perPage);
    }

    public function findWithRelations(string $uuid): Article
    {
        return Article::with(['author', 'reviewer'])->withCount('revisions')->findOrFail($uuid);
    }

    public function findWithAuthor(string $uuid): ?Article
    {
        return Article::with('author')->find($uuid);
    }

    public function findPublicBySlug(string $slug): Article
    {
        return $this->publicQuery()->with('author')->where('slug', $slug)->firstOrFail();
    }

    public function findForUpdate(string $uuid): ?Article
    {
        return Article::whereKey($uuid)->lockForUpdate()->first();
    }

    public function slugExists(string $slug, ?string $ignoreUuid = null): bool
    {
        return Article::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreUuid, fn (Builder $query, string $uuid) => $query->whereKeyNot($uuid))
            ->exists();
    }

    public function uuidsDueBefore(Carbon $moment): Collection
    {
        return Article::where('status', ArticleStatus::Scheduled)
            ->where('publish_at', '<=', $moment)
            ->orderBy('publish_at')
            ->pluck('uuid');
    }

    public function incrementViews(string $uuid): void
    {
        Article::whereKey($uuid)->toBase()->increment('view_count');
    }

    public function countsByStatus(): array
    {
        return Article::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function oldestInReview(int $limit): Collection
    {
        return Article::with('author')
            ->where('status', ArticleStatus::InReview)
            ->orderBy('updated_at')
            ->orderBy('uuid')
            ->limit($limit)
            ->get();
    }

    public function countAuthoredBy(string $adminUuid): int
    {
        return Article::withTrashed()
            ->where(fn (Builder $query) => $query->where('author_admin_uuid', $adminUuid)->orWhere('created_by_admin_uuid', $adminUuid))
            ->count();
    }

    public function save(Article $article): Article
    {
        $article->save();

        return $article;
    }

    public function delete(Article $article): void
    {
        $article->delete();
    }

    private function publicQuery(): Builder
    {
        return Article::where('status', ArticleStatus::Published)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    private function searched(Builder $query, ?string $search): Builder
    {
        if (! $search) {
            return $query;
        }

        $term = '%'.addcslashes($search, '\%_').'%';

        return $query->where(fn (Builder $inner) => $inner
            ->where('title', 'like', $term)
            ->orWhere('excerpt', 'like', $term));
    }
}

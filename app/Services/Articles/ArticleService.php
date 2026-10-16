<?php

namespace App\Services\Articles;

use App\Enums\ArticleStatus;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Models\Admin;
use App\Models\Article;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ArticleService
{
    public const DEFAULT_PER_PAGE = 15;

    public const TRANSACTION_ATTEMPTS = 3;

    private const SLUG_MAX_LENGTH = 120;

    private const EXCERPT_LENGTH = 200;

    private const FIELDS = ['title', 'slug', 'excerpt', 'body', 'category', 'tags'];

    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly ArticleRevisionService $revisions,
        private readonly ArticleHtmlSanitizer $sanitizer,
        private readonly AuditLogService $audit,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->articles->paginateAdmin($filters, $this->perPage($filters));
    }

    public function show(string $uuid): Article
    {
        return $this->articles->findWithRelations($uuid);
    }

    public function publicList(array $filters): LengthAwarePaginator
    {
        return $this->articles->paginatePublic($filters, $this->perPage($filters));
    }

    public function publicShow(string $slug): Article
    {
        $article = $this->articles->findPublicBySlug($slug);

        $this->articles->incrementViews($article->getKey());
        $article->view_count++;
        $article->syncOriginalAttribute('view_count');

        return $article;
    }

    public function create(array $data, Admin $admin): Article
    {
        $coverUrl = FileUploadHelper::upload($data['cover_image'] ?? null, config('apfspn.uploads.folders.article_covers'));

        $uuid = DB::transaction(function () use ($data, $admin, $coverUrl) {
            $article = new Article;

            $this->fillContent($article, $data);
            $article->fill([
                'slug' => $data['slug'] ?? $this->uniqueSlug($data['title']),
                'cover_image_url' => $coverUrl,
                'status' => ArticleStatus::Draft,
                'author_admin_uuid' => $admin->getKey(),
                'created_by_admin_uuid' => $admin->getKey(),
                'updated_by_admin_uuid' => $admin->getKey(),
            ]);

            $this->articles->save($article);
            $this->revisions->record($article, $admin->getKey());
            $this->audit->record('article.created', $article);

            return $article->getKey();
        }, self::TRANSACTION_ATTEMPTS);

        return $this->show($uuid);
    }

    public function update(string $uuid, array $data, Admin $admin): Article
    {
        $coverUrl = array_key_exists('cover_image', $data)
            ? FileUploadHelper::upload($data['cover_image'], config('apfspn.uploads.folders.article_covers'))
            : null;

        DB::transaction(function () use ($uuid, $data, $admin, $coverUrl) {
            $article = $this->lockedEditable($uuid, $admin);

            if (isset($data['slug']) && $data['slug'] !== $article->slug && $article->published_at !== null) {
                throw ValidationException::withMessages(['slug' => 'The slug cannot be changed after the article has been published.']);
            }

            $this->fillContent($article, $data);

            if ($coverUrl !== null) {
                $article->cover_image_url = $coverUrl;
            }

            $this->saveWithRevision($article, $admin, 'article.updated');
        }, self::TRANSACTION_ATTEMPTS);

        return $this->show($uuid);
    }

    public function restoreRevision(string $uuid, string $revisionUuid, Admin $admin): Article
    {
        DB::transaction(function () use ($uuid, $revisionUuid, $admin) {
            $article = $this->lockedEditable($uuid, $admin);
            $revision = $this->revisions->find($article->getKey(), $revisionUuid);

            $article->fill($revision->only(ArticleRevisionService::CONTENT_FIELDS));

            $this->saveWithRevision($article, $admin, 'article.revision_restored', ['revision_uuid' => $revision->getKey()]);
        }, self::TRANSACTION_ATTEMPTS);

        return $this->show($uuid);
    }

    public function revisions(string $uuid, array $filters): LengthAwarePaginator
    {
        $article = $this->show($uuid);

        return $this->revisions->paginate($article->getKey(), $this->perPage($filters));
    }

    public function delete(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $article = $this->locked($uuid);

            if (! $article->status->isDeletable()) {
                throw new ApiException('Only draft or archived articles can be deleted.', 422, [
                    'code' => 'invalid_status_transition',
                    'current_status' => $article->status->value,
                ]);
            }

            $this->audit->record('article.deleted', $article);
            $this->articles->delete($article);
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function locked(string $uuid): Article
    {
        return $this->articles->findForUpdate($uuid)
            ?? throw (new ModelNotFoundException)->setModel(Article::class, [$uuid]);
    }

    public function perPage(array $filters): int
    {
        return (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
    }

    private function lockedEditable(string $uuid, Admin $admin): Article
    {
        $article = $this->locked($uuid);

        Gate::forUser($admin)->authorize('update', $article);

        if (! $article->status->isEditable()) {
            throw new ApiException('Archived articles cannot be edited. Unarchive the article first.', 422, [
                'code' => 'invalid_status_transition',
                'current_status' => $article->status->value,
            ]);
        }

        return $article;
    }

    private function saveWithRevision(Article $article, Admin $admin, string $action, array $extra = []): void
    {
        $changes = $this->audit->changeSet($article);

        if ($changes['changed_fields'] === []) {
            return;
        }

        unset($changes['previous']['body']);

        $article->updated_by_admin_uuid = $admin->getKey();
        $this->articles->save($article);

        if ($this->revisions->contentChanged($article)) {
            $this->revisions->record($article, $admin->getKey());
        }

        $this->audit->record($action, $article, $changes + $extra);
    }

    private function fillContent(Article $article, array $data): void
    {
        $article->fill(Arr::only($data, self::FIELDS));

        if (array_key_exists('body', $data)) {
            $article->body = $this->sanitizer->sanitize($data['body']);
        }

        if (array_key_exists('tags', $data)) {
            $article->tags = $this->normaliseTags($data['tags'] ?? []);
        }

        if (blank($article->excerpt)) {
            $article->excerpt = $this->excerptFrom($article->body);
        }
    }

    private function normaliseTags(array $tags): array
    {
        return array_values(array_unique(array_filter(array_map(fn (string $tag) => Str::lower(trim($tag)), $tags))));
    }

    private function excerptFrom(string $html): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5)));

        return $text === '' ? null : Str::limit($text, self::EXCERPT_LENGTH);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), self::SLUG_MAX_LENGTH - 6, '') ?: 'article';
        $slug = $base;
        $suffix = 2;

        while ($this->articles->slugExists($slug)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}

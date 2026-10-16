<?php

namespace App\Services\Articles;

use App\Enums\ArticleNotice;
use App\Enums\ArticleStatus;
use App\Exceptions\ApiException;
use App\Jobs\SendArticleNoticeEmail;
use App\Models\Admin;
use App\Models\Article;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ArticleWorkflowService
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly ArticleService $service,
        private readonly AuditLogService $audit,
    ) {}

    public function submit(string $uuid, Admin $admin): Article
    {
        return $this->transition($uuid, $admin, 'article.submitted', function (Article $article) use ($admin) {
            Gate::forUser($admin)->authorize('submit', $article);

            if ($article->status === ArticleStatus::InReview) {
                return false;
            }

            if (! $article->status->canBeSubmitted()) {
                $this->refuse($article, 'Only drafts or articles with requested changes can be submitted for review.');
            }

            $article->status = ArticleStatus::InReview;

            return true;
        });
    }

    public function publish(string $uuid, Admin $admin): Article
    {
        return $this->transition($uuid, $admin, 'article.published', function (Article $article) use ($admin) {
            if ($article->status === ArticleStatus::Published) {
                return false;
            }

            if (! $article->status->canBePublished()) {
                $this->refuse($article, 'Archived articles must be unarchived instead of published.');
            }

            $this->markPublished($article, $admin);

            return true;
        });
    }

    public function schedule(string $uuid, Carbon $publishAt, Admin $admin): Article
    {
        if (! $publishAt->isFuture()) {
            throw ValidationException::withMessages(['publish_at' => 'The publish time must be in the future.']);
        }

        return $this->transition($uuid, $admin, 'article.scheduled', function (Article $article) use ($publishAt, $admin) {
            if ($article->status === ArticleStatus::Scheduled && $article->publish_at?->equalTo($publishAt)) {
                return false;
            }

            if (! $article->status->canBeScheduled()) {
                $this->refuse($article, 'Only unpublished articles can be scheduled.');
            }

            $article->fill([
                'status' => ArticleStatus::Scheduled,
                'publish_at' => $publishAt,
                'reviewed_by_admin_uuid' => $admin->getKey(),
            ]);

            return true;
        });
    }

    public function unschedule(string $uuid, Admin $admin): Article
    {
        return $this->transition($uuid, $admin, 'article.unscheduled', function (Article $article) {
            if ($article->status === ArticleStatus::InReview) {
                return false;
            }

            if ($article->status !== ArticleStatus::Scheduled) {
                $this->refuse($article, 'Only scheduled articles can be unscheduled.');
            }

            $article->fill(['status' => ArticleStatus::InReview, 'publish_at' => null]);

            return true;
        });
    }

    public function sendBack(string $uuid, string $note, Admin $admin): Article
    {
        return $this->transition($uuid, $admin, 'article.sent_back', function (Article $article) use ($note, $admin) {
            if ($article->status === ArticleStatus::ChangesRequested) {
                return false;
            }

            if (! $article->status->canBeSentBack()) {
                $this->refuse($article, 'Only articles in review or scheduled can be sent back.');
            }

            $article->fill([
                'status' => ArticleStatus::ChangesRequested,
                'review_note' => $note,
                'reviewed_by_admin_uuid' => $admin->getKey(),
                'publish_at' => null,
            ]);

            $this->notifyAuthor($article, ArticleNotice::SentBack, $admin);

            return true;
        });
    }

    public function archive(string $uuid, Admin $admin): Article
    {
        return $this->transition($uuid, $admin, 'article.archived', function (Article $article) {
            if ($article->status === ArticleStatus::Archived) {
                return false;
            }

            if ($article->status !== ArticleStatus::Published) {
                $this->refuse($article, 'Only published articles can be archived.');
            }

            $article->fill(['status' => ArticleStatus::Archived, 'archived_at' => now()]);

            return true;
        });
    }

    public function unarchive(string $uuid, Admin $admin): Article
    {
        return $this->transition($uuid, $admin, 'article.unarchived', function (Article $article) {
            if ($article->status === ArticleStatus::Published) {
                return false;
            }

            if ($article->status !== ArticleStatus::Archived) {
                $this->refuse($article, 'Only archived articles can be unarchived.');
            }

            $article->fill(['status' => ArticleStatus::Published, 'archived_at' => null]);

            return true;
        });
    }

    public function publishDue(bool $dryRun = false): int
    {
        $uuids = $this->articles->uuidsDueBefore(now());

        if ($dryRun) {
            return $uuids->count();
        }

        return $uuids->sum(fn (string $uuid) => DB::transaction(function () use ($uuid) {
            $article = $this->articles->findForUpdate($uuid);

            if ($article?->status !== ArticleStatus::Scheduled || $article->publish_at === null || $article->publish_at->isFuture()) {
                return 0;
            }

            $this->markPublished($article, null);
            $this->articles->save($article);

            return 1;
        }, ArticleService::TRANSACTION_ATTEMPTS));
    }

    private function markPublished(Article $article, ?Admin $admin): void
    {
        $article->fill([
            'status' => ArticleStatus::Published,
            'published_at' => now(),
            'publish_at' => $admin ? null : $article->publish_at,
            'reviewed_by_admin_uuid' => $admin?->getKey() ?? $article->reviewed_by_admin_uuid,
        ]);

        $this->notifyAuthor($article, ArticleNotice::Published, $admin);
    }

    private function notifyAuthor(Article $article, ArticleNotice $notice, ?Admin $actor): void
    {
        if ($article->author_admin_uuid === null || $article->author_admin_uuid === $actor?->getKey()) {
            return;
        }

        SendArticleNoticeEmail::dispatch($article->getKey(), $notice, (string) now()->getTimestamp())->afterCommit();
    }

    private function transition(string $uuid, Admin $admin, string $action, Closure $apply): Article
    {
        DB::transaction(function () use ($uuid, $admin, $action, $apply) {
            $article = $this->service->locked($uuid);

            if (! $apply($article)) {
                return;
            }

            $article->updated_by_admin_uuid = $admin->getKey();
            $changes = $this->audit->changeSet($article);
            $this->articles->save($article);
            $this->audit->record($action, $article, $changes);
        }, ArticleService::TRANSACTION_ATTEMPTS);

        return $this->service->show($uuid);
    }

    private function refuse(Article $article, string $message): never
    {
        throw new ApiException($message, 422, [
            'code' => 'invalid_status_transition',
            'current_status' => $article->status->value,
        ]);
    }
}

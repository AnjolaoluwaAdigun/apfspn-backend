<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Articles\ArchiveArticleRequest;
use App\Http\Requests\Admin\Articles\DeleteArticleRequest;
use App\Http\Requests\Admin\Articles\ListArticleRevisionsRequest;
use App\Http\Requests\Admin\Articles\ListArticlesRequest;
use App\Http\Requests\Admin\Articles\PublishArticleRequest;
use App\Http\Requests\Admin\Articles\RestoreArticleRevisionRequest;
use App\Http\Requests\Admin\Articles\ScheduleArticleRequest;
use App\Http\Requests\Admin\Articles\SendBackArticleRequest;
use App\Http\Requests\Admin\Articles\ShowArticleRequest;
use App\Http\Requests\Admin\Articles\StoreArticleRequest;
use App\Http\Requests\Admin\Articles\SubmitArticleRequest;
use App\Http\Requests\Admin\Articles\UnarchiveArticleRequest;
use App\Http\Requests\Admin\Articles\UnscheduleArticleRequest;
use App\Http\Requests\Admin\Articles\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\ArticleRevisionResource;
use App\Models\Article;
use App\Services\Articles\ArticleService;
use App\Services\Articles\ArticleWorkflowService;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ArticleController extends Controller
{
    public function __construct(
        private readonly ArticleService $articles,
        private readonly ArticleWorkflowService $workflow,
    ) {}

    public function index(ListArticlesRequest $request): JsonResponse
    {
        return JsonResponser::success(ArticleResource::collection($this->articles->list($request->validated())));
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        $article = $this->articles->create($request->validated(), $request->user());

        return JsonResponser::success(new ArticleResource($article), 'Article created.', 201);
    }

    public function show(ShowArticleRequest $request, Article $article): JsonResponse
    {
        return JsonResponser::success(new ArticleResource($this->articles->show($article->getKey())));
    }

    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        $updated = $this->articles->update($article->getKey(), $request->validated(), $request->user());

        return JsonResponser::success(new ArticleResource($updated), 'Article updated.');
    }

    public function destroy(DeleteArticleRequest $request, Article $article): JsonResponse
    {
        $this->articles->delete($article->getKey());

        return JsonResponser::success(null, 'Article deleted.');
    }

    public function submit(SubmitArticleRequest $request, Article $article): JsonResponse
    {
        return $this->respond($this->workflow->submit($article->getKey(), $request->user()), 'Article submitted for review.');
    }

    public function publish(PublishArticleRequest $request, Article $article): JsonResponse
    {
        return $this->respond($this->workflow->publish($article->getKey(), $request->user()), 'Article published.');
    }

    public function schedule(ScheduleArticleRequest $request, Article $article): JsonResponse
    {
        $publishAt = Carbon::parse($request->validated()['publish_at']);

        return $this->respond($this->workflow->schedule($article->getKey(), $publishAt, $request->user()), 'Article scheduled.');
    }

    public function unschedule(UnscheduleArticleRequest $request, Article $article): JsonResponse
    {
        return $this->respond($this->workflow->unschedule($article->getKey(), $request->user()), 'Article unscheduled.');
    }

    public function sendBack(SendBackArticleRequest $request, Article $article): JsonResponse
    {
        $note = $request->validated()['review_note'];

        return $this->respond($this->workflow->sendBack($article->getKey(), $note, $request->user()), 'Article sent back to the author.');
    }

    public function archive(ArchiveArticleRequest $request, Article $article): JsonResponse
    {
        return $this->respond($this->workflow->archive($article->getKey(), $request->user()), 'Article archived.');
    }

    public function unarchive(UnarchiveArticleRequest $request, Article $article): JsonResponse
    {
        return $this->respond($this->workflow->unarchive($article->getKey(), $request->user()), 'Article unarchived.');
    }

    public function revisions(ListArticleRevisionsRequest $request, Article $article): JsonResponse
    {
        return JsonResponser::success(ArticleRevisionResource::collection($this->articles->revisions($article->getKey(), $request->validated())));
    }

    public function restoreRevision(RestoreArticleRevisionRequest $request, Article $article, string $revision): JsonResponse
    {
        $restored = $this->articles->restoreRevision($article->getKey(), $revision, $request->user());

        return JsonResponser::success(new ArticleResource($restored), 'Revision restored.');
    }

    private function respond(Article $article, string $message): JsonResponse
    {
        return JsonResponser::success(new ArticleResource($article), $message);
    }
}

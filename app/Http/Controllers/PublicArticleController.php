<?php

namespace App\Http\Controllers;

use App\Http\Requests\Public\Articles\ListPublicArticlesRequest;
use App\Http\Requests\Public\Articles\ShowPublicArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Services\Articles\ArticleService;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;

class PublicArticleController extends Controller
{
    public function __construct(private readonly ArticleService $articles) {}

    public function index(ListPublicArticlesRequest $request): JsonResponse
    {
        return JsonResponser::success(ArticleResource::collection($this->articles->publicList($request->validated())));
    }

    public function show(ShowPublicArticleRequest $request, string $slug): JsonResponse
    {
        return JsonResponser::success(new ArticleResource($this->articles->publicShow($slug)));
    }
}

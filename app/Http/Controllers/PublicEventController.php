<?php

namespace App\Http\Controllers;

use App\Http\Requests\Public\Events\ListPublicEventsRequest;
use App\Http\Requests\Public\Events\ShowPublicEventRequest;
use App\Http\Resources\EventResource;
use App\Services\Events\EventService;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;

class PublicEventController extends Controller
{
    public function __construct(private readonly EventService $events) {}

    public function index(ListPublicEventsRequest $request): JsonResponse
    {
        return JsonResponser::success(EventResource::collection($this->events->publicList($request->validated())));
    }

    public function show(ShowPublicEventRequest $request, string $slug): JsonResponse
    {
        return JsonResponser::success(new EventResource($this->events->publicShow($slug)));
    }
}

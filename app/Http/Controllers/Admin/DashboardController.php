<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dashboard\ShowDashboardRequest;
use App\Http\Resources\AdminDashboardResource;
use App\Services\Dashboard\AdminDashboardService;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardService $dashboard) {}

    public function show(ShowDashboardRequest $request): JsonResponse
    {
        return JsonResponser::success(new AdminDashboardResource($this->dashboard->forAdmin($request->user())));
    }
}

<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\Dashboard\ShowDashboardRequest;
use App\Http\Resources\MemberDashboardResource;
use App\Services\Dashboard\MemberDashboardService;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly MemberDashboardService $dashboard) {}

    public function show(ShowDashboardRequest $request): JsonResponse
    {
        return JsonResponser::success(new MemberDashboardResource($this->dashboard->forMember($request->user())));
    }
}

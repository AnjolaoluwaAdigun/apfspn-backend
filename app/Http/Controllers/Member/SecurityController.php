<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\Security\ShowSecurityRequest;
use App\Http\Requests\Member\Security\UpdateSecurityRequest;
use App\Http\Resources\SecuritySettingsResource;
use App\Services\Auth\SecuritySettingsService;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;

class SecurityController extends Controller
{
    public function __construct(private readonly SecuritySettingsService $security) {}

    public function show(ShowSecurityRequest $request): JsonResponse
    {
        return JsonResponser::success(new SecuritySettingsResource($this->security->show($request->user())));
    }

    public function update(UpdateSecurityRequest $request): JsonResponse
    {
        $data = $request->validated();
        $settings = $this->security->update($request->user(), (bool) $data['two_factor_enabled'], $data['current_password'] ?? null);

        return JsonResponser::success(new SecuritySettingsResource($settings), 'Security settings updated.');
    }
}

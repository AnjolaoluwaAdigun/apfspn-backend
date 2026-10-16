<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\Profile\ShowProfileRequest;
use App\Http\Requests\Member\Profile\UpdateProfileRequest;
use App\Http\Resources\MemberResource;
use App\Services\Membership\MemberProfileService;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function __construct(private readonly MemberProfileService $profiles) {}

    public function show(ShowProfileRequest $request): JsonResponse
    {
        return JsonResponser::success(new MemberResource($this->profiles->show($request->user())));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $member = $this->profiles->update($request->user(), $request->validated());

        return JsonResponser::success(new MemberResource($member), 'Profile updated.');
    }
}

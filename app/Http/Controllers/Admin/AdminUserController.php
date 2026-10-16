<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\AdminAuditLogRequest;
use App\Http\Requests\Admin\Users\DeleteAdminRequest;
use App\Http\Requests\Admin\Users\DisableAdminRequest;
use App\Http\Requests\Admin\Users\EnableAdminRequest;
use App\Http\Requests\Admin\Users\InviteAdminRequest;
use App\Http\Requests\Admin\Users\ListAdminsRequest;
use App\Http\Requests\Admin\Users\ResendInviteRequest;
use App\Http\Requests\Admin\Users\ShowAdminRequest;
use App\Http\Requests\Admin\Users\UpdateAdminRequest;
use App\Http\Resources\AdminResource;
use App\Http\Resources\AuditLogResource;
use App\Models\Admin;
use App\Services\AdminUsers\AdminUserService;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    public function __construct(private readonly AdminUserService $admins) {}

    public function index(ListAdminsRequest $request): JsonResponse
    {
        return JsonResponser::success(AdminResource::collection($this->admins->list($request->validated())));
    }

    public function store(InviteAdminRequest $request): JsonResponse
    {
        return JsonResponser::success(new AdminResource($this->admins->invite($request->validated(), $request->user())), 'Invite sent.', 201);
    }

    public function show(ShowAdminRequest $request, Admin $admin): JsonResponse
    {
        return JsonResponser::success(new AdminResource($this->admins->show($admin->getKey())));
    }

    public function update(UpdateAdminRequest $request, Admin $admin): JsonResponse
    {
        $updated = $this->admins->update($admin->getKey(), $request->validated(), $request->user());

        return JsonResponser::success(new AdminResource($updated), 'Admin updated.');
    }

    public function resendInvite(ResendInviteRequest $request, Admin $admin): JsonResponse
    {
        return JsonResponser::success(new AdminResource($this->admins->resendInvite($admin->getKey(), $request->user())), 'Invite sent.');
    }

    public function disable(DisableAdminRequest $request, Admin $admin): JsonResponse
    {
        return JsonResponser::success(new AdminResource($this->admins->disable($admin->getKey(), $request->user())), 'Admin disabled.');
    }

    public function enable(EnableAdminRequest $request, Admin $admin): JsonResponse
    {
        return JsonResponser::success(new AdminResource($this->admins->enable($admin->getKey(), $request->user())), 'Admin enabled.');
    }

    public function destroy(DeleteAdminRequest $request, Admin $admin): JsonResponse
    {
        $this->admins->delete($admin->getKey(), $request->user());

        return JsonResponser::success(null, 'Admin deleted.');
    }

    public function auditLogs(AdminAuditLogRequest $request, Admin $admin): JsonResponse
    {
        return JsonResponser::success(AuditLogResource::collection($this->admins->auditTrail($admin->getKey(), $request->validated())));
    }
}

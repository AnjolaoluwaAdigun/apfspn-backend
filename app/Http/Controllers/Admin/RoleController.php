<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Roles\DeleteRoleRequest;
use App\Http\Requests\Admin\Roles\ListPermissionsRequest;
use App\Http\Requests\Admin\Roles\ListRolesRequest;
use App\Http\Requests\Admin\Roles\ShowRoleRequest;
use App\Http\Requests\Admin\Roles\StoreRoleRequest;
use App\Http\Requests\Admin\Roles\UpdateRoleRequest;
use App\Http\Resources\PermissionGroupResource;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\AdminUsers\RoleService;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(ListRolesRequest $request): JsonResponse
    {
        return JsonResponser::success(RoleResource::collection($this->roles->list()));
    }

    public function permissions(ListPermissionsRequest $request): JsonResponse
    {
        return JsonResponser::success(PermissionGroupResource::collection($this->roles->permissions()));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        return JsonResponser::success(new RoleResource($this->roles->create($request->validated(), $request->user())), 'Role created.', 201);
    }

    public function show(ShowRoleRequest $request, Role $role): JsonResponse
    {
        return JsonResponser::success(new RoleResource($this->roles->show($role->getKey())));
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        return JsonResponser::success(new RoleResource($this->roles->update($role->getKey(), $request->validated(), $request->user())), 'Role updated.');
    }

    public function destroy(DeleteRoleRequest $request, Role $role): JsonResponse
    {
        $this->roles->delete($role->getKey());

        return JsonResponser::success(null, 'Role deleted.');
    }
}

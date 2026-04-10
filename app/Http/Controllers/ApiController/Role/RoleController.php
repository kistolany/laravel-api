<?php

namespace App\Http\Controllers\ApiController\Role;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\RoleAssignPermissionsRequest;
use App\Http\Requests\Role\RoleStoreRequest;
use App\Http\Requests\Role\RoleUpdateRequest;
use App\Http\Resources\Role\RolePermissionAssignmentResource;
use App\Http\Resources\Role\RoleResource;
use App\Services\Role\RoleService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private RoleService $service)
    {
    }

    public function index(): JsonResponse
    {
        $roles = $this->service->index();

        $data = $roles
            ->map(fn ($role) => (new RoleResource($role))->resolve())
            ->values();

        return $this->success($data, 'Roles retrieved successfully.');
    }

    public function store(RoleStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = $this->service->create($data);

        return $this->success(new RoleResource($role), 'Role created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $role = $this->service->findById($id);

        return $this->success(new RoleResource($role), 'Role retrieved successfully.');
    }

    public function update(RoleUpdateRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        $role = $this->service->update($id, $data);

        return $this->success(new RoleResource($role), 'Role updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->success(null, 'Role deleted successfully.');
    }

    public function assignPermissions(RoleAssignPermissionsRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        $payload = $this->service->preparePermissionAssignmentPayload($data);
        $permissionIds = $payload['permission_ids'];
        $mode = $payload['mode'];
        $role = $this->service->assignPermissions($id, $permissionIds, $mode);

        return $this->success(
            new RolePermissionAssignmentResource(['role' => $role, 'mode' => $mode]),
            'Role permissions updated successfully.'
        );
    }
}


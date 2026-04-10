<?php

namespace App\Http\Controllers\ApiController\Permission;

use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\PermissionStoreRequest;
use App\Http\Requests\Permission\PermissionUpdateRequest;
use App\Http\Resources\Permission\PermissionResource;
use App\Services\Permission\PermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private PermissionService $service)
    {
    }

    public function index(): JsonResponse
    {
        $permissions = $this->service->index();

        $data = $permissions
            ->map(fn ($permission) => (new PermissionResource($permission))->resolve())
            ->values();

        return $this->success($data, 'Permissions retrieved successfully.');
    }

    public function store(PermissionStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $permission = $this->service->create($data);

        return $this->success(new PermissionResource($permission), 'Permission created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $permission = $this->service->findById($id);

        return $this->success(new PermissionResource($permission), 'Permission retrieved successfully.');
    }

    public function update(PermissionUpdateRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        $permission = $this->service->update($id, $data);

        return $this->success(new PermissionResource($permission), 'Permission updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->success(null, 'Permission deleted successfully.');
    }
}


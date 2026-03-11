<?php

namespace App\Http\Controllers\ApiController;

use App\Http\Controllers\Controller;
use App\Services\PermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private PermissionService $service)
    {
    }

    public function index(): JsonResponse
    {
        $permissions = $this->service->index();

        $data = $permissions->map(fn ($permission) => $this->formatPermission($permission));

        return $this->success($data, 'Permissions retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')],
        ]);

        $permission = $this->service->create($data);

        return $this->success([
            'id' => $permission->id,
            'name' => $permission->name,
        ], 'Permission created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $permission = $this->service->findById($id);

        return $this->success($this->formatPermission($permission), 'Permission retrieved successfully.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($id)],
        ]);

        $permission = $this->service->update($id, $data);

        return $this->success($this->formatPermission($permission), 'Permission updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->success(null, 'Permission deleted successfully.');
    }

    private function formatPermission($permission): array
    {
        return [
            'id' => $permission->id,
            'name' => $permission->name,
        ];
    }
}

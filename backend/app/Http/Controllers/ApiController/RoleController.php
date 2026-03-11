<?php

namespace App\Http\Controllers\ApiController;

use App\Http\Controllers\Controller;
use App\Services\RoleService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private RoleService $service)
    {
    }

    public function index(): JsonResponse
    {
        $roles = $this->service->index();

        $data = $roles->map(fn ($role) => $this->formatRole($role));

        return $this->success($data, 'Roles retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')],
            'description' => ['nullable', 'string'],
        ]);

        $role = $this->service->create($data);

        return $this->success([
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
        ], 'Role created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $role = $this->service->findById($id);

        return $this->success($this->formatRole($role), 'Role retrieved successfully.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($id)],
            'description' => ['nullable', 'string'],
        ]);

        $role = $this->service->update($id, $data);

        return $this->success($this->formatRole($role), 'Role updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->success(null, 'Role deleted successfully.');
    }

    public function assignPermissions(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'permission_ids' => ['nullable', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'permissions' => ['nullable', 'array', 'min:1'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'mode' => ['nullable', 'in:add,sync'],
        ]);

        $permissionIds = $data['permission_ids'] ?? [];

        if (empty($permissionIds) && !empty($data['permissions'])) {
            $permissionIds = \App\Models\Permission::whereIn('name', $data['permissions'])
                ->pluck('id')
                ->all();
        }

        if (empty($permissionIds)) {
            return $this->error('permission_ids or permissions is required.');
        }

        $mode = $data['mode'] ?? 'sync';
        $role = $this->service->assignPermissions($id, $permissionIds, $mode);

        return $this->success([
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->values(),
            'mode' => $mode,
        ], 'Role permissions updated successfully.');
    }

    private function formatRole($role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => $role->permissions?->pluck('name')->values() ?? [],
        ];
    }
}

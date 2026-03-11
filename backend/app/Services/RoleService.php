<?php

namespace App\Services;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;

class RoleService
{
    public function index(): Collection
    {
        return Role::with('permissions')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Role
    {
        return Role::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
    }

    public function findById(int $id): Role
    {
        $role = Role::with('permissions')->find($id);

        if (!$role) {
            throw new ApiException(ResponseStatus::NOT_FOUND, 'Role not found.');
        }

        return $role;
    }

    public function update(int $id, array $data): Role
    {
        $role = $this->findById($id);

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return $role->load('permissions');
    }

    public function delete(int $id): void
    {
        $role = Role::find($id);

        if (!$role) {
            throw new ApiException(ResponseStatus::NOT_FOUND, 'Role not found.');
        }

        $role->delete();
    }

    public function assignPermissions(int $roleId, array $permissionIds, string $mode = 'sync'): Role
    {
        $role = Role::find($roleId);

        if (!$role) {
            throw new ApiException(ResponseStatus::NOT_FOUND, 'Role not found.');
        }

        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
        $existingIds = Permission::whereIn('id', $permissionIds)->pluck('id')->all();
        $missing = array_values(array_diff($permissionIds, $existingIds));

        if (!empty($missing)) {
            throw new ApiException(
                ResponseStatus::NOT_FOUND,
                'Permission not found.',
                data: ['missing_permission_ids' => $missing]
            );
        }

        if ($mode === 'add') {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        } else {
            $role->permissions()->sync($permissionIds);
        }

        return $role->load('permissions');
    }
}

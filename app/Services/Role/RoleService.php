<?php

namespace App\Services\Role;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\Permission;
use App\Models\Role;
use App\Support\RbacPermissionCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Services\Concerns\ServiceTraceable;
class RoleService
{
    use ServiceTraceable;

    public function index(): Collection
    {
        return $this->trace(__FUNCTION__, function (): Collection {
            return Role::with('permissions')
                ->orderBy('name')
                ->get();
            
            
        });
    }

    public function create(array $data): Role
    {
        return $this->trace(__FUNCTION__, function () use ($data): Role {
            return Role::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);
            
            
        });
    }

    public function findById(int $id): Role
    {
        return $this->trace(__FUNCTION__, function () use ($id): Role {
            $role = Role::with('permissions')->find($id);
            
            if (!$role) {
                Log::warning('Role not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, 'Role not found.');
            }
            
            return $role;
            
            
        });
    }

    public function update(int $id, array $data): Role
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): Role {
            $role = $this->findById($id);
            
            $role->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);
            
            return $role->load('permissions');
            
            
        });
    }

    public function delete(int $id): void
    {
        $this->trace(__FUNCTION__, function () use ($id) {
            $role = Role::find($id);
            
            if (!$role) {
                Log::warning('Role delete failed: not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, 'Role not found.');
            }
            
            $role->delete();
            
            
        });
    }

    public function assignPermissions(int $roleId, array $permissionIds, string $mode = 'sync'): Role
    {
        return $this->trace(__FUNCTION__, function () use ($roleId, $permissionIds, $mode): Role {
            $role = Role::find($roleId);
            
            if (!$role) {
                Log::warning('Assign permissions failed: role not found.', ['role_id' => $roleId]);
                throw new ApiException(ResponseStatus::NOT_FOUND, 'Role not found.');
            }
            
            $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
            $existingIds = Permission::whereIn('id', $permissionIds)->pluck('id')->all();
            $missing = array_values(array_diff($permissionIds, $existingIds));
            
            if (!empty($missing)) {
                Log::warning('Assign permissions failed: missing permission ids.', [
                    'role_id' => $roleId,
                    'missing_permission_ids' => $missing,
                ]);
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
            
            $role->load('permissions');
            $attachedIds = $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->all();
            $notAttached = array_values(array_diff($permissionIds, $attachedIds));
            
            if (!empty($notAttached)) {
                Log::warning('Assign permissions failed: permissions not attached.', [
                    'role_id' => $roleId,
                    'not_assigned_permission_ids' => $notAttached,
                ]);
                throw new ApiException(
                    ResponseStatus::BAD_REQUEST,
                    'Failed to assign some permissions to role.',
                    data: ['not_assigned_permission_ids' => $notAttached]
                );
            }
            
            return $role;
            
            
        });
    }

    public function preparePermissionAssignmentPayload(array $data): array
    {
        return $this->trace(__FUNCTION__, function () use ($data): array {
            $permissionIds = $data['permission_ids'] ?? [];
            
            if (empty($permissionIds) && !empty($data['permissions'])) {
                $permissionNames = collect($data['permissions'])
                    ->map(fn ($name) => trim((string) $name))
                    ->filter()
                    ->unique()
                    ->values();

                if ($permissionNames->isEmpty()) {
                    throw new ApiException(ResponseStatus::BAD_REQUEST, 'permission_ids or permissions is required.');
                }

                $catalogNames = collect(RbacPermissionCatalog::all());
                $unknownNames = $permissionNames
                    ->reject(fn ($name) => $catalogNames->contains($name))
                    ->values()
                    ->all();

                if (!empty($unknownNames)) {
                    Log::warning('Permission assignment payload invalid: unknown permissions.', [
                        'unknown_permissions' => $unknownNames,
                    ]);
                    throw new ApiException(
                        ResponseStatus::BAD_REQUEST,
                        'Unknown permissions provided.',
                        data: ['unknown_permissions' => $unknownNames]
                    );
                }

                $existingPermissions = Permission::whereIn('name', $permissionNames)->get()->keyBy('name');
                $missingNames = $permissionNames
                    ->reject(fn ($name) => $existingPermissions->has($name))
                    ->values();

                foreach ($missingNames as $name) {
                    $permission = Permission::firstOrCreate(['name' => $name]);
                    $existingPermissions->put($name, $permission);
                }

                $permissionIds = $permissionNames
                    ->map(fn ($name) => (int) $existingPermissions->get($name)->id)
                    ->values()
                    ->all();
            }
            
            if (empty($permissionIds)) {
                Log::warning('Permission assignment payload invalid: empty permissions.', [
                    'payload_keys' => array_keys($data),
                ]);
                throw new ApiException(ResponseStatus::BAD_REQUEST, 'permission_ids or permissions is required.');
            }
            
            return [
                'permission_ids' => $permissionIds,
                'mode' => $data['mode'] ?? 'sync',
            ];
            
            
        });
    }
}




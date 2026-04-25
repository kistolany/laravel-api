<?php

namespace App\Services\Permission;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\Permission;
use App\Support\RbacPermissionCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Services\Concerns\ServiceTraceable;
class PermissionService
{
    use ServiceTraceable;

    public function index(): Collection
    {
        return $this->trace(__FUNCTION__, function (): Collection {
            $storedPermissions = Permission::query()
                ->orderBy('name')
                ->get()
                ->keyBy('name');

            $catalogPermissions = collect(RbacPermissionCatalog::all())
                ->map(function (string $name) use ($storedPermissions): array {
                    $stored = $storedPermissions->get($name);

                    return [
                        'id' => $stored?->id,
                        'name' => $name,
                        'available' => $stored !== null,
                    ];
                });

            $customStoredPermissions = $storedPermissions
                ->reject(fn (Permission $permission, string $name) => RbacPermissionCatalog::contains($name))
                ->map(fn (Permission $permission): array => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'available' => true,
                ])
                ->values();

            return $catalogPermissions
                ->concat($customStoredPermissions)
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
        });
    }

    public function create(array $data): Permission
    {
        return $this->trace(__FUNCTION__, function () use ($data): Permission {
            return Permission::create([
                'name' => $data['name'],
            ]);
            
            
        });
    }

    public function findById(int $id): Permission
    {
        return $this->trace(__FUNCTION__, function () use ($id): Permission {
            $permission = Permission::find($id);
            
            if (!$permission) {
                Log::warning('Permission not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, 'Permission not found.');
            }
            
            return $permission;
            
            
        });
    }

    public function update(int $id, array $data): Permission
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): Permission {
            $permission = $this->findById($id);
            
            $permission->update([
                'name' => $data['name'],
            ]);
            
            return $permission;
            
            
        });
    }

    public function delete(int $id): void
    {
        $this->trace(__FUNCTION__, function () use ($id) {
            $permission = Permission::find($id);
            
            if (!$permission) {
                Log::warning('Permission delete failed: not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, 'Permission not found.');
            }
            
            $permission->delete();
            
            
        });
    }
}




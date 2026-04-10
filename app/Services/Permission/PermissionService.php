<?php

namespace App\Services\Permission;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\Permission;
use Illuminate\Support\Collection;
use App\Services\Concerns\ServiceTraceable;
class PermissionService
{
    use ServiceTraceable;

    public function index(): Collection
    {
        return $this->trace(__FUNCTION__, function (): Collection {
            return Permission::query()
                ->orderBy('name')
                ->get();
            
            
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
                throw new ApiException(ResponseStatus::NOT_FOUND, 'Permission not found.');
            }
            
            $permission->delete();
            
            
        });
    }
}




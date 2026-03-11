<?php

namespace App\Services;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\Permission;
use Illuminate\Support\Collection;

class PermissionService
{
    public function index(): Collection
    {
        return Permission::query()
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Permission
    {
        return Permission::create([
            'name' => $data['name'],
        ]);
    }

    public function findById(int $id): Permission
    {
        $permission = Permission::find($id);

        if (!$permission) {
            throw new ApiException(ResponseStatus::NOT_FOUND, 'Permission not found.');
        }

        return $permission;
    }

    public function update(int $id, array $data): Permission
    {
        $permission = $this->findById($id);

        $permission->update([
            'name' => $data['name'],
        ]);

        return $permission;
    }

    public function delete(int $id): void
    {
        $permission = Permission::find($id);

        if (!$permission) {
            throw new ApiException(ResponseStatus::NOT_FOUND, 'Permission not found.');
        }

        $permission->delete();
    }
}

<?php

namespace App\Http\Resources\Role;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RolePermissionAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->resource['role'];
        $mode = $this->resource['mode'];

        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->values(),
            'mode' => $mode,
        ];
    }
}


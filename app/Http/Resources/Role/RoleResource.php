<?php

namespace App\Http\Resources\Role;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
        ];

        if ($this->relationLoaded('permissions')) {
            $data['permissions'] = $this->permissions?->pluck('name')->values() ?? [];
        }

        return $data;
    }
}



<?php

namespace App\Http\Resources\Permission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $id = data_get($this->resource, 'id');

        return [
            'id' => $id,
            'name' => data_get($this->resource, 'name'),
            'available' => data_get($this->resource, 'available', $id !== null),
        ];
    }
}



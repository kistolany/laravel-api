<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DistrictResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'province_id' => $this->province_id,
            'name' => $this->name,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CommuneResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'district_id' => $this->district_id,
            'name' => $this->name,
        ];
    }
}

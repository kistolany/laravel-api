<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DistrictResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       return [
        'id' => $this->id,
        'province_id' => $this->province_id,
        'name_kh' => $this->name_kh,
        'name_en' => $this->name_en,
        
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
        ];
    }
}

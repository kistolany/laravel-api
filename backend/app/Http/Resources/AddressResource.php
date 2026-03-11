<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'address_type' => $this->address_type,
            'house_number' => $this->house_number,
            'street_number' => $this->street_number,
            'village' => $this->village,
            'province_id' => $this->province_id,
            'district_id' => $this->district_id,
            'commune_id' => $this->commune_id,
            'province' => new ProvinceResource($this->whenLoaded('province')),
            'district' => new DistrictResource($this->whenLoaded('district')),
            'commune' => new CommuneResource($this->whenLoaded('commune')),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'student_id'    => $this->student_id,
            'address_type'  => $this->address_type,
            'house_number'  => $this->house_number,
            'street_number' => $this->street_number,
            'village'       => $this->village,
            
            // Return raw IDs
            'province_id'   => $this->province_id,
            'district_id'   => $this->district_id,
            'commune_id'    => $this->commune_id,

            // Return Names (assuming relationships are defined in the Model)
            'province_name' => $this->province->name_en ?? null,
            'district_name' => $this->district->name_en ?? null,
            'commune_name'  => $this->commune->name_en ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
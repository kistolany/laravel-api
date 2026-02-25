<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{

    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'barcode'           => $this->barcode,
            'full_name_kh'      => $this->full_name_kh,
            'full_name_en'      => $this->full_name_en,
            'gender'            => $this->gender,
            'dob'               => $this->dob,
            'phone' => $this->phone,
            'email' => $this->email,
            'id_card_number'    => $this->id_card_number,
            'image'             => $this->image,
            'status'            => $this->status,

            // 'whenLoaded' is great because it prevents unnecessary SQL queries
            'academic_details'  => new AcademicInfoResource($this->whenLoaded('academicInfo')),
            'addresses'         => AddressResource::collection($this->whenLoaded('addresses')),
            'classes'           => StudentClassResource::collection($this->whenLoaded('classes')),

            // Safety check: only format if the timestamp exists
            'created_at'        => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'        => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

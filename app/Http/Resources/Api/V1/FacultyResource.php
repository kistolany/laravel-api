<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacultyResource extends JsonResource
{

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name_kh' => $this->name_kh,
            'name_eg' => $this->name_eg,
        ];
    }
}

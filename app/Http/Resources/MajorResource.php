<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MajorResource extends JsonResource
{

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'faculty_id'=>$this->faculty_id,
            'name_kh' => $this->name_kh,
            'name_eg' => $this->name_eg,
        ];
    }
}

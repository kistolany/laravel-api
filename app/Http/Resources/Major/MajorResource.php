<?php

namespace App\Http\Resources\Major;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MajorResource extends JsonResource
{

    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'faculty_id'   => $this->faculty_id,
            'faculty_name' => $this->faculty?->name ?? null,
            'name'         => $this->name,
        ];
    }
}



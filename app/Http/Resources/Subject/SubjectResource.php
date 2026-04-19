<?php

namespace App\Http\Resources\Subject;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
{

    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'subject_code' => $this->subject_Code,
            'name'         => $this->name,
        ];
    }
}



<?php

namespace App\Http\Resources\Major;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MajorSubjectResource extends JsonResource
{

    public function toArray($request): array
    {
      return [
            'id'           => $this->id,
            'year_level'   => $this->year_level,
            'semester'     => $this->semester,
            
            // Getting data from the Subject relationship
            'subject_id'   => $this->subject_id,
            'subject_code' => $this->subject->subject_Code ?? null,
            'name'         => $this->subject->name ?? null,
            
            // Getting data from the Major relationship
            'major_id'     => $this->major_id,
            'major_name'   => $this->major->name ?? null,
        ];
    }
}



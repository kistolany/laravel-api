<?php

namespace App\Http\Resources;

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
            'name_kh'      => $this->subject->name_kh ?? null,
            'name_eg'      => $this->subject->name_eg ?? null,
            
            // Getting data from the Major relationship
            'major_id'     => $this->major_id,
            'major_name'   => $this->major->name_eg ?? null, 
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
{

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'subject_Code'=>$this->subject_Code,
            'name_kh' => $this->name_kh,
            'name_eg' => $this->name_eg,
        ];
    }
}

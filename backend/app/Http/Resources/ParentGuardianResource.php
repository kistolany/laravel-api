<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ParentGuardianResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'father_name'    => $this->father_name,
            'father_job'     => $this->father_job,
            'mother_name'    => $this->mother_name,
            'mother_job'     => $this->mother_job,
            'guardian_name'  => $this->guardian_name,
            'guardian_job'   => $this->guardian_job,
            'guardian_phone' => $this->guardian_phone,
        ];
    }
}

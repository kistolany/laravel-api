<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentRegistrationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'student_id'           => $this->student_id,
            'student'              => new StudentResource($this->whenLoaded('student')),
            'high_school_name'     => $this->high_school_name,
            'high_school_province' => $this->high_school_province,
            'bacii_exam_year'      => $this->bacii_exam_year,
            'bacii_grade'          => $this->bacii_grade,
            'target_degree'        => $this->target_degree,
            'diploma_attached'     => $this->diploma_attached,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}

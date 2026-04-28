<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentRegistrationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'student_id'           => $this->student_id,
            'student'              => new StudentResource($this->whenLoaded('student')),
            'admission_path'       => $this->admission_path,
            'high_school_name'     => $this->high_school_name,
            'high_school_province' => $this->high_school_province,
            'previous_school_name' => $this->previous_school_name,
            'previous_school_province' => $this->previous_school_province,
            'completed_year_level' => $this->completed_year_level,
            'placement_notes'      => $this->placement_notes,
            'bacii_exam_year'      => $this->bacii_exam_year,
            'bacii_grade'          => $this->bacii_grade,
            'target_degree'        => $this->target_degree,
            'diploma_attached'     => $this->diploma_attached,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}



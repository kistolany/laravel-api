<?php

namespace App\Http\Resources\Scholarship;

use App\Http\Resources\Student\StudentResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ScholarshipResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'student_id'             => $this->student_id,
            'student'                => new StudentResource($this->whenLoaded('student')),
            'nationality'            => $this->nationality,
            'ethnicity'              => $this->ethnicity,
            'emergency_name'         => $this->emergency_name,
            'emergency_relation'     => $this->emergency_relation,
            'emergency_phone'        => $this->emergency_phone,
            'emergency_address'      => $this->emergency_address,
            'grade'                  => $this->grade,
            'exam_year'              => $this->exam_year,
            'guardians_address'      => $this->guardians_address,
            'guardians_phone_number' => $this->guardians_phone_number,
            'guardians_email'        => $this->guardians_email,
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
        ];
    }
}



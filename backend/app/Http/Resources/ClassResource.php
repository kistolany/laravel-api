<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClassResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'major_id' => $this->major_id,
            'shift_id' => $this->shift_id,
            'academic_year' => $this->academic_year,
            'year_level' => $this->year_level,
            'semester' => $this->semester,
            'section' => $this->section,
            'max_students' => $this->max_students,
            'is_active' => $this->is_active,
            'students' => ClassStudentResource::collection($this->whenLoaded('students')),
        ];
    }
}

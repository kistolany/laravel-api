<?php

namespace App\Http\Resources\Class;

use Illuminate\Http\Resources\Json\JsonResource;

class ClassProgramResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'major_id'      => $this->major_id,
            'major'         => $this->relationLoaded('major') && $this->major ? $this->major->name : null,
            'major_name'    => $this->relationLoaded('major') && $this->major ? $this->major->name : null,
            'shift_id'      => $this->shift_id,
            'shift'         => $this->relationLoaded('shift') && $this->shift ? $this->shift->name : null,
            'year'          => $this->year_level ? (string) $this->year_level : null,
            'year_level'    => $this->year_level,
            'semester'      => $this->semester,
            'academic_year' => $this->academic_year,
            'section'       => $this->section,
            'max_students'  => $this->max_students,
        ];
    }
}

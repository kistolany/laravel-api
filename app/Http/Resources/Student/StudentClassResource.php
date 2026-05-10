<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentClassResource extends JsonResource
{
    public function toArray($request): array
    {
        $joinedDate = $this->pivot?->joined_date;
        $leftDate = $this->pivot?->left_date;

        $program = $this->relationLoaded('programs') ? $this->programs->first() : null;

        return [
            'class_id'      => $this->id,
            'name'          => $this->name,
            'academic_year' => $program?->academic_year,
            'year_level'    => $program?->year_level,
            'semester'      => $program?->semester,
            'section'       => $program?->section,
            'status'        => $this->pivot?->status,
            'joined_date' => $joinedDate instanceof \Illuminate\Support\Carbon
                ? $joinedDate->format('Y-m-d')
                : $joinedDate,
            'left_date' => $leftDate instanceof \Illuminate\Support\Carbon
                ? $leftDate->format('Y-m-d')
                : $leftDate,
        ];
    }
}



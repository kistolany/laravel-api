<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentClassResource extends JsonResource
{
    public function toArray($request): array
    {
        $joinedDate = $this->pivot?->joined_date;
        $leftDate = $this->pivot?->left_date;

        return [
            'class_id' => $this->id,
            'code' => $this->code,
            'academic_year' => $this->academic_year,
            'year_level' => $this->year_level,
            'semester' => $this->semester,
            'section' => $this->section,
            'status' => $this->pivot?->status,
            'joined_date' => $joinedDate instanceof \Illuminate\Support\Carbon
                ? $joinedDate->format('Y-m-d')
                : $joinedDate,
            'left_date' => $leftDate instanceof \Illuminate\Support\Carbon
                ? $leftDate->format('Y-m-d')
                : $leftDate,
        ];
    }
}

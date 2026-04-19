<?php

namespace App\Http\Resources\Class;

use Illuminate\Http\Resources\Json\JsonResource;

class ClassStudentResource extends JsonResource
{
    public function toArray($request): array
    {
        $joinedDate = $this->pivot?->joined_date;

        $dob = $this->dob;

        return [
            'student_id'   => $this->barcode,
            'full_name_en' => $this->full_name_en,
            'full_name_kh' => $this->full_name_kh,
            'gender'       => $this->gender,
            'dob'          => $dob instanceof \Illuminate\Support\Carbon
                ? $dob->format('Y-m-d')
                : ($dob ? substr((string) $dob, 0, 10) : null),
            'major_name'   => $this->relationLoaded('academicInfo') ? $this->academicInfo?->major?->name : null,
            'batch_year'   => $this->relationLoaded('academicInfo') ? $this->academicInfo?->batch_year : null,
            'stage'        => $this->relationLoaded('academicInfo') ? $this->academicInfo?->stage : null,
            'class_name'   => $this->additional['class_name'] ?? null,
            'status'       => $this->pivot?->status,
            'joined_date'  => $joinedDate instanceof \Illuminate\Support\Carbon
                ? $joinedDate->format('Y-m-d')
                : $joinedDate,
        ];
    }
}

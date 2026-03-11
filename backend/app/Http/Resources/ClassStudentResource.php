<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClassStudentResource extends JsonResource
{
    public function toArray($request): array
    {
        $joinedDate = $this->pivot?->joined_date;

        return [
            'student_id' => $this->barcode,
            'full_name_en' => $this->full_name_en,
            'status' => $this->pivot?->status,
            'joined_date' => $joinedDate instanceof \Illuminate\Support\Carbon
                ? $joinedDate->format('Y-m-d')
                : $joinedDate,
        ];
    }
}

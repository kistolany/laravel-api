<?php

namespace App\Http\Resources\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherAvailabilitySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'teacher_id' => data_get($this->resource, 'teacher_id'),
            'teacher_name' => data_get($this->resource, 'teacher_name'),
            'subject' => data_get($this->resource, 'subject'),
            'availability' => TeacherAvailabilityResource::collection(
                collect(data_get($this->resource, 'availability', []))
            ),
        ];
    }
}

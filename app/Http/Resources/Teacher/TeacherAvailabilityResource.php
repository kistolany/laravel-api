<?php

namespace App\Http\Resources\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherAvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'teacher_id' => data_get($this->resource, 'teacher_id'),
            'teacher_name' => $this->teacherName(),
            'subject_id' => data_get($this->resource, 'subject_id'),
            'subject_name' => data_get($this->resource, 'subject.name'),
            'shift_id' => data_get($this->resource, 'shift_id'),
            'shift_name' => data_get($this->resource, 'shift.name'),
            'shift_time' => data_get($this->resource, 'shift.time_range'),
            'day_of_week' => data_get($this->resource, 'day_of_week'),
        ];
    }

    private function teacherName(): ?string
    {
        $teacher = data_get($this->resource, 'teacher');

        if (! $teacher) {
            return null;
        }

        return trim(data_get($teacher, 'first_name') . ' ' . data_get($teacher, 'last_name'));
    }
}

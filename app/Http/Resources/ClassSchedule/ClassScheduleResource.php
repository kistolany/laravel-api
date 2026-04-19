<?php

namespace App\Http\Resources\ClassSchedule;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'class_id'      => $this->class_id,
            'class_name'    => $this->classroom?->name,
            'subject_id'    => $this->subject_id,
            'subject_name'  => $this->subject?->name_eg,
            'teacher_id'    => $this->teacher_id,
            'teacher_name'  => $this->teacher ? trim($this->teacher->first_name . ' ' . $this->teacher->last_name) : null,
            'shift_id'      => $this->shift_id,
            'shift'         => $this->shift?->name,
            'day_of_week'   => $this->day_of_week,
            'academic_year' => $this->academic_year,
            'year_level'    => $this->year_level,
            'semester'      => $this->semester,
            'room'          => $this->room,
        ];
    }
}

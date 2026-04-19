<?php

namespace App\Http\Resources\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'academic_year' => $this->academic_year,
            'year_level' => $this->year_level,
            'semester' => $this->semester,
            'section' => $this->section,
            'max_students' => $this->max_students,
            'is_active' => $this->is_active,
            'student_count' => $this->students_count ?? null,
            'major' => $this->major ? [
                'id' => $this->major->id,
                'name_en' => $this->major->name_eg,
                'name_kh' => $this->major->name_kh,
            ] : null,
            'shift' => $this->shift ? [
                'id' => $this->shift->id,
                'name' => $this->shift->name,
                'time_range' => $this->shift->time_range,
            ] : null,
        ];
    }
}



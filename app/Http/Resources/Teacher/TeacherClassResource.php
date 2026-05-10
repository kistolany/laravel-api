<?php

namespace App\Http\Resources\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'is_active'     => $this->is_active,
            'student_count' => $this->students_count ?? null,
            'programs'      => $this->whenLoaded('programs', fn() => $this->programs->map(fn($p) => [
                'major_id'      => $p->major_id,
                'major_name'    => $p->major?->name,
                'shift_id'      => $p->shift_id,
                'shift_name'    => $p->shift?->name,
                'year_level'    => $p->year_level,
                'semester'      => $p->semester,
                'academic_year' => $p->academic_year,
                'section'       => $p->section,
            ])->values()),
        ];
    }
}



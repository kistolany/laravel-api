<?php

namespace App\Http\Resources\Class;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Class\ClassProgramResource;

class ClassResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name ?? $this->code,
            'code'          => $this->code ?? $this->name,
            'academic_year' => $this->academic_year,
            'year_level'    => $this->year_level,
            'semester'      => $this->semester,
            'section'       => $this->section,
            'max_students'  => $this->max_students,
            'is_active'     => $this->is_active,
            'major'         => $this->whenLoaded('major', fn() => [
                'id'   => $this->major->id,
                'name' => $this->major->name,
            ]),
            'shift'         => $this->whenLoaded('shift', fn() => [
                'id'         => $this->shift->id,
                'name'       => $this->shift->name,
                'time_range' => $this->shift->time_range ?? null,
            ]),
            'programs'      => $this->relationLoaded('schedules') && $this->schedules->isNotEmpty()
                ? $this->schedules->unique(fn($s) => ($s->major_id ?? '0') . '-' . $s->year_level . '-' . $s->semester . '-' . $s->shift_id)
                    ->map(fn($s) => [
                        'id'         => $s->id,
                        'major'      => $this->major?->name,
                        'major_name' => $this->major?->name,
                        'shift'      => $s->shift?->name,
                        'year'       => (string) $s->year_level,
                        'year_level' => $s->year_level,
                        'semester'   => $s->semester,
                    ])->values()
                : ClassProgramResource::collection($this->whenLoaded('programs')),
            'student_count' => $this->class_students_count ?? ($this->relationLoaded('students') ? $this->students->count() : $this->classStudents()->count()),
            'students'      => ClassStudentResource::collection($this->whenLoaded('students')),
        ];
    }
}

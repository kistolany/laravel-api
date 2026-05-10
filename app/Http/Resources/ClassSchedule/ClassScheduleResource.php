<?php

namespace App\Http\Resources\ClassSchedule;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $program = $this->classProgram;

        $program ??= $this->classroom?->programs?->first(function ($program) {
                if ($this->year_level && $program->year_level && (int) $program->year_level !== (int) $this->year_level) {
                    return false;
                }
                if ($this->semester && $program->semester && (int) $program->semester !== (int) $this->semester) {
                    return false;
                }
                if ($this->shift_id && $program->shift_id && (int) $program->shift_id !== (int) $this->shift_id) {
                    return false;
                }
                if ($this->academic_year && $program->academic_year && (string) $program->academic_year !== (string) $this->academic_year) {
                    return false;
                }
                return true;
            }) ?? $this->classroom?->programs?->first();

        $resolvedShift = $program?->shift ?? $this->shift;
        $resolvedShiftId = $program?->shift_id ?? $this->shift_id;

        return [
            'id'               => $this->id,
            'class_id'         => $this->class_id,
            'class_program_id' => $program?->id,
            'class_name'       => $this->classroom?->name,
            'major_id'         => $program?->major_id,
            'major_name'       => $program?->major?->name,
            'subject_id'       => $this->subject_id,
            'subject_name'     => $this->subject?->name,
            'teacher_id'       => $this->teacher_id,
            'teacher_name'     => $this->teacher ? trim($this->teacher->first_name . ' ' . $this->teacher->last_name) : null,
            'teacher_code'     => $this->teacher?->teacher_id,
            'teacher_username' => $this->teacher?->username,
            'teacher_email'    => $this->teacher?->email,
            'shift_id'         => $resolvedShiftId,
            'shift'            => $resolvedShift?->name,
            'day_of_week'      => $this->day_of_week,
            'academic_year'    => $this->academic_year,
            'year_level'       => $this->year_level,
            'semester'         => $this->semester,
            'total_male'       => (int) ($this->total_male ?? 0),
            'total_female'     => (int) ($this->total_female ?? 0),
            'total_students'   => (int) ($this->total_male ?? 0) + (int) ($this->total_female ?? 0),
            'room_id'          => $this->room_id,
            'room_name'        => $this->roomModel?->name,
            'room_building'    => $this->roomModel?->building,
            'room_capacity'    => $this->roomModel?->capacity,
            'code'             => $this->code,
            'start_date'       => $this->start_date?->toDateString(),
            'end_date'         => $this->end_date?->toDateString(),
        ];
    }
}

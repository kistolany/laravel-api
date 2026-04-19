<?php

namespace App\Http\Resources\AcademicInfo;

use App\Http\Resources\Major\MajorResource;
use App\Http\Resources\Shift\ShiftResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademicInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'major' => ($this->resource && $this->resource->relationLoaded('major') && $this->resource->major) ? [
                'id'           => $this->resource->major->id,
                'faculty_id'   => $this->resource->major->faculty_id,
                'faculty_name' => $this->resource->major->faculty?->name ?? null,
                'name'         => $this->resource->major->name,
            ] : null,
            'shift' => new ShiftResource($this->whenLoaded('shift')),
            'batch_year' => $this->batch_year,
            'stage' => $this->stage,
            'study_days' => $this->study_days
        ];
    }
}



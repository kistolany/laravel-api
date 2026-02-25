<?php

namespace App\Http\Resources;

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
            'major' => new MajorResource($this->whenLoaded('major')),
            'shift' => new ShiftResource($this->whenLoaded('shift')),
            'batch_year' => $this->batch_year,
            'stage' => $this->stage,
            'study_days' => $this->study_days
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\DTOs\AttendanceSessionCreateData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceSessionCreateResource extends JsonResource
{
    /** @var AttendanceSessionCreateData */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'class_id' => $this->resource->class_id,
            'subject_id' => $this->resource->subject_id,
            'session_date' => $this->resource->session_date,
            'session_number' => $this->resource->session_number,
        ];
    }
}

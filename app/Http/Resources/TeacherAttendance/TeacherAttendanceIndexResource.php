<?php

namespace App\Http\Resources\TeacherAttendance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherAttendanceIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

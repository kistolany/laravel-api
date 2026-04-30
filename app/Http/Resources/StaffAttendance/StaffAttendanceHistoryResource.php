<?php

namespace App\Http\Resources\StaffAttendance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffAttendanceHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

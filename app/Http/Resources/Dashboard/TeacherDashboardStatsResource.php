<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherDashboardStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

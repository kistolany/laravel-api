<?php

namespace App\Http\Resources\SubjectClassroom;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectClassroomOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

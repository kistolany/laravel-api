<?php

namespace App\Http\Resources\AcademicSetting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademicTermResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->name,
            'code' => $this->code,
            'number' => $this->number,
            'value' => $this->number ?? $this->id,
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'active' => (bool) $this->is_active,
        ];
    }
}

<?php

namespace App\Http\Resources\Holiday;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class HolidayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $documentPath = data_get($this->resource, 'document_path');
        $id = data_get($this->resource, 'id');

        return [
            'id' => $id,
            'name' => data_get($this->resource, 'name'),
            'type' => data_get($this->resource, 'type'),
            'start_date' => data_get($this->resource, 'start_date'),
            'end_date' => data_get($this->resource, 'end_date'),
            'description' => data_get($this->resource, 'description', ''),
            'document_name' => data_get($this->resource, 'document_name'),
            'document_url' => $documentPath ? Storage::disk('public')->url($documentPath) : null,
            'document_preview_url' => $documentPath ? "/api/v1/holidays/{$id}/document" : null,
            'created_at' => data_get($this->resource, 'created_at'),
        ];
    }
}

<?php

namespace App\Http\Resources\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'title' => data_get($this->resource, 'title'),
            'body' => data_get($this->resource, 'body'),
            'audience' => data_get($this->resource, 'audience'),
            'priority' => data_get($this->resource, 'priority'),
            'target_user_id' => data_get($this->resource, 'target_user_id'),
            'created_at' => data_get($this->resource, 'created_at'),
            'sent_by_name' => data_get($this->resource, 'sent_by_name'),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'site' => $this->site->name,
            'page_url' => $this->payload['page_url'] ?? null,
            'fields' => $this->payload['fields'] ?? [],
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurred_at,
        ];
    }
}

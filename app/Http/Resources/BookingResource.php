<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
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
            'event_id' => $this->event_id,
            'reference' => $this->reference,
            'status' => $this->status,
            'total' => $this->total,
            'created_at' => $this->created_at?->toISOString(),
            'event' => new EventResource($this->whenLoaded('event')),
            'items' => BookingItemResource::collection($this->whenLoaded('items')),
        ];
    }
}

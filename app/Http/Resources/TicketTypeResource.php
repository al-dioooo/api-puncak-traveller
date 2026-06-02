<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id ?? (string) $this->id,
            'eventId' => $this->event?->public_id ?? (string) $this->event_id,
            'name' => $this->name,
            'description' => $this->description ?? 'Standard participant access',
            'price' => (int) $this->price,
            'currency' => $this->currency ?? 'IDR',
            'stock' => $this->remaining,
            'capacityLabel' => $this->capacity_label ?? ($this->remaining > 0 ? "{$this->remaining} left" : 'Sold out'),
            'maxPerUser' => $this->when($this->max_per_user !== null, $this->max_per_user),
        ];
    }
}

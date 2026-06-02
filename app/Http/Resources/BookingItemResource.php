<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemResource extends JsonResource
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
            'ticket_type_id' => $this->ticket_type_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'ticket_type' => new TicketTypeResource($this->whenLoaded('ticketType')),
        ];
    }
}

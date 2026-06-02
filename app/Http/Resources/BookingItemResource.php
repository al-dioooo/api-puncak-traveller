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
            'ticketTierId' => $this->ticketType?->public_id ?? (string) $this->ticket_type_id,
            'ticketName' => $this->ticketType?->name ?? 'Ticket',
            'quantity' => (int) $this->quantity,
            'unitPrice' => (int) $this->unit_price,
            'lineTotal' => (int) $this->quantity * (int) $this->unit_price,
        ];
    }
}

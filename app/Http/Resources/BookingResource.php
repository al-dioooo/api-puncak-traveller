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
            'id' => (string) $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'paymentStatus' => $this->payment_status,
            'paymentProvider' => $this->payment_provider,
            'snapToken' => $this->snap_token,
            'snapRedirectUrl' => $this->snap_redirect_url,
            'midtransOrderId' => $this->midtrans_order_id,
            'eventId' => $this->event?->public_id ?? (string) $this->event_id,
            'eventSlug' => $this->event?->slug,
            'attendeeName' => $this->attendee_name,
            'attendeeEmail' => $this->attendee_email,
            'items' => BookingItemResource::collection($this->whenLoaded('items')),
            'subtotal' => (int) $this->subtotal,
            'bookingFee' => (int) $this->booking_fee,
            'total' => (int) $this->total,
            'currency' => $this->currency ?? 'IDR',
            'paidAt' => $this->paid_at?->toIso8601String(),
            'paymentFailedAt' => $this->payment_failed_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}

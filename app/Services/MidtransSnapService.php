<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransSnapService
{
    /**
     * @return array{token: string, redirect_url?: string}
     */
    public function createTransaction(Booking $booking): array
    {
        $serverKey = (string) config('services.midtrans.server_key');

        if ($serverKey === '') {
            throw new RuntimeException('Midtrans server key is not configured.');
        }

        $booking->loadMissing(['user', 'event', 'items.ticketType']);

        $payload = [
            'transaction_details' => [
                'order_id' => $booking->midtrans_order_id ?: $booking->reference,
                'gross_amount' => (int) $booking->total,
            ],
            'customer_details' => [
                'first_name' => $booking->attendee_name ?: $booking->user?->name,
                'email' => $booking->attendee_email ?: $booking->user?->email,
            ],
            'item_details' => $this->itemDetails($booking),
        ];

        $notificationUrl = config('services.midtrans.notification_url');

        if ($notificationUrl) {
            $payload['notification_url'] = $notificationUrl;
        }

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->post((string) config('services.midtrans.snap_api_url'), $payload)
            ->throw()
            ->json();

        return [
            'token' => (string) $response['token'],
            'redirect_url' => isset($response['redirect_url']) ? (string) $response['redirect_url'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasValidSignature(array $payload): bool
    {
        $serverKey = (string) config('services.midtrans.server_key');
        $signature = (string) ($payload['signature_key'] ?? '');

        if ($serverKey === '' || $signature === '') {
            return false;
        }

        $expected = hash('sha512', implode('', [
            (string) ($payload['order_id'] ?? ''),
            (string) ($payload['status_code'] ?? ''),
            (string) ($payload['gross_amount'] ?? ''),
            $serverKey,
        ]));

        return hash_equals($expected, $signature);
    }

    /**
     * @return array<int, array{id: string, price: int, quantity: int, name: string}>
     */
    private function itemDetails(Booking $booking): array
    {
        $items = $booking->items
            ->map(fn ($item): array => [
                'id' => (string) ($item->ticketType?->public_id ?? $item->ticket_type_id),
                'price' => (int) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'name' => str((string) ($item->ticketType?->name ?? 'Ticket'))->limit(50, '')->toString(),
            ])
            ->values()
            ->all();

        if ((int) $booking->booking_fee > 0) {
            $items[] = [
                'id' => 'booking-fee',
                'price' => (int) $booking->booking_fee,
                'quantity' => 1,
                'name' => 'Booking fee',
            ];
        }

        return $items;
    }
}

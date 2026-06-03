<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

class BookingPaymentService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyMidtransStatus(Booking $booking, array $payload): Booking
    {
        return match ((string) ($payload['transaction_status'] ?? '')) {
            'settlement' => $this->markPaid($booking, $payload),
            'capture' => $this->applyMidtransCaptureStatus($booking, $payload),
            'pending' => $this->markPending($booking, $payload),
            'deny', 'cancel', 'expire', 'failure' => $this->markFailed($booking, 'Midtrans payment did not complete.', $payload),
            'refund', 'partial_refund' => $this->markRefunded($booking, $payload, 'Refunded by Midtrans.'),
            default => $this->markPending($booking, $payload),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function markPending(Booking $booking, array $payload = []): Booking
    {
        return DB::transaction(function () use ($booking, $payload): Booking {
            $booking = $this->lockBooking($booking);
            $booking->fill([
                ...$this->midtransFields($payload),
                'status' => Booking::STATUS_PENDING,
                'payment_status' => Booking::PAYMENT_PENDING,
            ])->save();

            return $booking->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function markPaid(Booking $booking, array $payload = []): Booking
    {
        return DB::transaction(function () use ($booking, $payload): Booking {
            $booking = $this->lockBooking($booking);
            $booking->fill([
                ...$this->midtransFields($payload),
                'status' => Booking::STATUS_CONFIRMED,
                'payment_status' => Booking::PAYMENT_PAID,
                'paid_at' => $booking->paid_at ?? now(),
                'payment_failed_at' => null,
                'cancellation_reason' => null,
            ])->save();

            return $booking->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function markFailed(Booking $booking, string $reason, array $payload = []): Booking
    {
        return DB::transaction(function () use ($booking, $reason, $payload): Booking {
            $booking = $this->lockBooking($booking);
            $this->releaseStock($booking);

            $booking->fill([
                ...$this->midtransFields($payload),
                'status' => Booking::STATUS_CANCELLED,
                'payment_status' => Booking::PAYMENT_FAILED,
                'cancelled_at' => $booking->cancelled_at ?? now(),
                'payment_failed_at' => $booking->payment_failed_at ?? now(),
                'cancellation_reason' => $reason,
            ])->save();

            return $booking->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function markRefunded(Booking $booking, array $payload = [], string $reason = 'Refunded.'): Booking
    {
        return DB::transaction(function () use ($booking, $payload, $reason): Booking {
            $booking = $this->lockBooking($booking);
            $this->releaseStock($booking);

            $booking->fill([
                ...$this->midtransFields($payload),
                'status' => Booking::STATUS_REFUNDED,
                'payment_status' => Booking::PAYMENT_REFUNDED,
                'cancelled_at' => $booking->cancelled_at ?? now(),
                'cancellation_reason' => $reason,
            ])->save();

            return $booking->refresh();
        });
    }

    public function releaseFailedBooking(Booking $booking, string $reason): Booking
    {
        return $this->markFailed($booking, $reason);
    }

    public function cancelBooking(Booking $booking, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $reason): Booking {
            $booking = $this->lockBooking($booking);
            $this->releaseStock($booking);

            $booking->fill([
                'status' => Booking::STATUS_CANCELLED,
                'payment_status' => $booking->payment_status === Booking::PAYMENT_PENDING
                    ? Booking::PAYMENT_FAILED
                    : $booking->payment_status,
                'cancelled_at' => $booking->cancelled_at ?? now(),
                'payment_failed_at' => $booking->payment_status === Booking::PAYMENT_PENDING
                    ? ($booking->payment_failed_at ?? now())
                    : $booking->payment_failed_at,
                'cancellation_reason' => $reason,
            ])->save();

            return $booking->refresh();
        });
    }

    private function lockBooking(Booking $booking): Booking
    {
        return Booking::query()
            ->whereKey($booking->getKey())
            ->with(['items.ticketType'])
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyMidtransCaptureStatus(Booking $booking, array $payload): Booking
    {
        return match ((string) ($payload['fraud_status'] ?? 'accept')) {
            'accept' => $this->markPaid($booking, $payload),
            'deny' => $this->markFailed($booking, 'Midtrans payment was denied by fraud detection.', $payload),
            default => $this->markPending($booking, $payload),
        };
    }

    private function releaseStock(Booking $booking): void
    {
        if ($booking->stock_released_at !== null) {
            return;
        }

        foreach ($booking->items as $item) {
            TicketType::query()
                ->whereKey($item->ticket_type_id)
                ->where('sold', '>=', $item->quantity)
                ->decrement('sold', (int) $item->quantity);
        }

        $booking->forceFill(['stock_released_at' => now()])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function midtransFields(array $payload): array
    {
        if ($payload === []) {
            return ['payment_provider' => 'midtrans'];
        }

        return [
            'payment_provider' => 'midtrans',
            'midtrans_transaction_id' => isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            'midtrans_payment_type' => isset($payload['payment_type']) ? (string) $payload['payment_type'] : null,
            'midtrans_status' => isset($payload['transaction_status']) ? (string) $payload['transaction_status'] : null,
            'midtrans_fraud_status' => isset($payload['fraud_status']) ? (string) $payload['fraud_status'] : null,
            'midtrans_payload' => $payload,
        ];
    }
}

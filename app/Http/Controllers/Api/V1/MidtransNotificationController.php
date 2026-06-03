<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingPaymentService;
use App\Services\MidtransSnapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MidtransNotificationController extends Controller
{
    public function __invoke(
        Request $request,
        MidtransSnapService $midtrans,
        BookingPaymentService $payments
    ): JsonResponse {
        $payload = $request->all();

        if (! $midtrans->hasValidSignature($payload)) {
            throw new AccessDeniedHttpException('Invalid Midtrans notification signature.');
        }

        $booking = Booking::query()
            ->where('midtrans_order_id', (string) ($payload['order_id'] ?? ''))
            ->orWhere('reference', (string) ($payload['order_id'] ?? ''))
            ->first();

        if ($booking === null) {
            throw new NotFoundHttpException('Booking not found for Midtrans order.');
        }

        $grossAmount = (int) round((float) ($payload['gross_amount'] ?? 0));

        if ($grossAmount !== (int) $booking->total) {
            throw new ConflictHttpException('Midtrans notification amount does not match booking total.');
        }

        $booking = match ((string) ($payload['transaction_status'] ?? '')) {
            'settlement' => $payments->markPaid($booking, $payload),
            'capture' => $this->handleCapture($booking, $payload, $payments),
            'pending' => $payments->markPending($booking, $payload),
            'deny', 'cancel', 'expire', 'failure' => $payments->markFailed($booking, 'Midtrans payment did not complete.', $payload),
            'refund', 'partial_refund' => $payments->markRefunded($booking, $payload, 'Refunded by Midtrans.'),
            default => $payments->markPending($booking, $payload),
        };

        return response()->json([
            'message' => 'Midtrans notification processed.',
            'data' => [
                'reference' => $booking->reference,
                'status' => $booking->status,
                'paymentStatus' => $booking->payment_status,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleCapture(Booking $booking, array $payload, BookingPaymentService $payments): Booking
    {
        return match ((string) ($payload['fraud_status'] ?? 'accept')) {
            'accept' => $payments->markPaid($booking, $payload),
            'deny' => $payments->markFailed($booking, 'Midtrans payment was denied by fraud detection.', $payload),
            default => $payments->markPending($booking, $payload),
        };
    }
}

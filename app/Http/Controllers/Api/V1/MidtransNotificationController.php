<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BookingPaymentService;
use App\Services\MidtransSnapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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

        $booking = $payments->applyMidtransStatus(
            $midtrans->bookingFromPayload($payload),
            $payload
        );

        return response()->json([
            'message' => 'Midtrans notification processed.',
            'data' => [
                'reference' => $booking->reference,
                'status' => $booking->status,
                'paymentStatus' => $booking->payment_status,
            ],
        ]);
    }
}

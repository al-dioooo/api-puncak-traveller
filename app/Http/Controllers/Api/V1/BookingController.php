<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Mail\BookingReceiptMail;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Services\BookingPaymentService;
use App\Services\MidtransSnapService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->string('status')->toString() === 'saved') {
            $savedEvents = $request->user()
                ->savedEvents()
                ->with(['event.ticketTypes'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'data' => $savedEvents->map(fn ($savedEvent): array => $this->savedEventCard($savedEvent->event))->values(),
            ]);
        }

        $bookingsQuery = Booking::query()
            ->with(['user', 'event', 'items.ticketType'])
            ->orderBy('created_at', 'desc');

        if ($request->user()->role !== User::ROLE_ADMIN) {
            $bookingsQuery->whereBelongsTo($request->user());
        }

        $this->applyStatusFilter($bookingsQuery, $request->string('status')->toString());

        $perPage = max(1, min($request->integer('per_page', 15), 50));
        $paginator = $bookingsQuery->paginate($perPage);
        $rows = $paginator->getCollection()
            ->map(fn (Booking $booking): array => $request->user()->role === User::ROLE_ADMIN
                ? $this->adminBookingRow($booking)
                : $this->bookingCard($booking))
            ->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(
        CreateBookingRequest $request,
        CreateBooking $createBooking,
        MidtransSnapService $midtrans,
        BookingPaymentService $payments
    ): JsonResponse {
        $event = $request->event();

        abort_if($event === null, 404, 'Event not found.');

        $booking = $createBooking->execute(
            $request->user(),
            $event,
            $request->bookingItems(),
            $request->validated('attendees', []),
            $request->idempotencyKey() ?: null
        );

        $booking = $this->ensureSnapTransaction($booking, $midtrans, $payments);

        return (new BookingResource($booking))->response()->setStatusCode(201);
    }

    public function show(Request $request, Booking $booking): BookingResource|JsonResponse
    {
        abort_unless($booking->user()->is($request->user()) || $request->user()->role === User::ROLE_ADMIN, 403);

        $booking->load(['user', 'event', 'items.ticketType']);

        if ($request->user()->role === User::ROLE_ADMIN) {
            return response()->json(['data' => $this->adminBookingDetail($booking)]);
        }

        return new BookingResource($booking);
    }

    public function cancel(Request $request, Booking $booking, BookingPaymentService $payments): JsonResponse
    {
        abort_unless($booking->user()->is($request->user()), 403);

        $booking->load(['event', 'items.ticketType']);

        if ($booking->event->starts_at->copy()->subDay()->lte(now())) {
            throw new ConflictHttpException('Bookings can only be cancelled at least 24 hours before the event starts.');
        }

        $booking = $payments->cancelBooking($booking, $request->string('reason')->toString());

        return response()->json([
            'data' => [
                'reference' => $booking->reference,
                'status' => $booking->status,
            ],
        ]);
    }

    public function refund(Booking $booking, BookingPaymentService $payments): JsonResponse
    {
        $booking->load(['items.ticketType']);

        if ($booking->status === Booking::STATUS_REFUNDED) {
            return response()->json([
                'message' => 'Booking refunded successfully.',
                'data' => [
                    'reference' => $booking->reference,
                    'status' => Booking::STATUS_REFUNDED,
                ],
            ]);
        }

        if (! in_array($booking->status, [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED], true)) {
            throw new ConflictHttpException('Only confirmed bookings can be refunded.');
        }

        $booking = $payments->markRefunded($booking, reason: 'Refunded by administrator.');

        return response()->json([
            'message' => 'Booking refunded successfully.',
            'data' => [
                'reference' => $booking->reference,
                'status' => Booking::STATUS_REFUNDED,
            ],
        ]);
    }

    public function resendReceipt(Booking $booking): JsonResponse
    {
        $booking->load(['user', 'event', 'items.ticketType']);
        $recipient = $booking->user?->email ?? $booking->attendee_email;

        if (! $recipient) {
            throw new ConflictHttpException('Booking does not have a receipt email address.');
        }

        Mail::to($recipient)->send(new BookingReceiptMail($booking));

        return response()->json([
            'message' => 'Booking receipt resent successfully.',
            'data' => [
                'reference' => $booking->reference,
                'resentAt' => now()->toIso8601String(),
            ],
        ]);
    }

    public function ticket(Request $request, Booking $booking): Response
    {
        abort_unless($booking->user()->is($request->user()) || $request->user()->role === User::ROLE_ADMIN, 403);

        if ($booking->payment_status !== Booking::PAYMENT_PAID) {
            throw new ConflictHttpException('Tickets are available after payment is completed.');
        }

        $booking->load(['user', 'event', 'items.ticketType']);
        $filename = str($booking->reference)
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-')
            ->append('-ticket.html')
            ->toString();

        return response(BookingReceiptMail::renderHtml($booking), 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function updatePaymentStatus(Request $request, Booking $booking, BookingPaymentService $payments): JsonResponse
    {
        $validated = $request->validate([
            'payment_status' => ['required', Rule::in([
                Booking::PAYMENT_PENDING,
                Booking::PAYMENT_PAID,
                Booking::PAYMENT_FAILED,
                Booking::PAYMENT_REFUNDED,
            ])],
        ]);

        $booking = match ($validated['payment_status']) {
            Booking::PAYMENT_PAID => $payments->markPaid($booking),
            Booking::PAYMENT_REFUNDED => $payments->markRefunded($booking, reason: 'Refunded by administrator.'),
            Booking::PAYMENT_FAILED => $payments->markFailed($booking, 'Payment marked as failed by administrator.'),
            default => $payments->markPending($booking),
        };

        return response()->json([
            'message' => 'Payment status updated successfully.',
            'data' => [
                'reference' => $booking->reference,
                'status' => $booking->status,
                'paymentStatus' => $booking->payment_status,
            ],
        ]);
    }

    public function syncPaymentStatus(
        Request $request,
        Booking $booking,
        MidtransSnapService $midtrans,
        BookingPaymentService $payments
    ): JsonResponse {
        if (! $this->canAccessBooking($request, $booking)) {
            throw new AccessDeniedHttpException('You are not allowed to refresh this booking payment status.');
        }

        $orderId = $booking->midtrans_order_id ?: $booking->reference;

        if ($booking->payment_provider !== 'midtrans' || ! $orderId) {
            throw new ConflictHttpException('Booking does not have a Midtrans payment to sync.');
        }

        $payload = $midtrans->getTransactionStatus($orderId);
        $booking = $payments->applyMidtransStatus(
            $midtrans->bookingFromPayload($payload),
            $payload
        );

        return response()->json([
            'message' => 'Payment status synced successfully.',
            'data' => [
                'reference' => $booking->reference,
                'status' => $booking->status,
                'paymentStatus' => $booking->payment_status,
            ],
        ]);
    }

    private function canAccessBooking(Request $request, Booking $booking): bool
    {
        $user = $request->user();

        return $user !== null
            && ($booking->user()->is($user) || $user->role === User::ROLE_ADMIN);
    }

    private function ensureSnapTransaction(
        Booking $booking,
        MidtransSnapService $midtrans,
        BookingPaymentService $payments
    ): Booking {
        $booking->loadMissing(['user', 'event', 'items.ticketType']);

        if ($booking->payment_status !== Booking::PAYMENT_PENDING || $booking->snap_token) {
            return $booking;
        }

        $wasRecentlyCreated = $booking->wasRecentlyCreated;

        try {
            $booking->forceFill([
                'payment_provider' => 'midtrans',
                'midtrans_order_id' => $booking->midtrans_order_id ?: $booking->reference,
            ])->save();

            $snap = $midtrans->createTransaction($booking);

            $booking->forceFill([
                'snap_token' => $snap['token'],
                'snap_redirect_url' => $snap['redirect_url'] ?? null,
            ])->save();

            return $booking->refresh()->load(['event', 'items.ticketType']);
        } catch (Throwable $exception) {
            if ($wasRecentlyCreated) {
                $failedBooking = $payments->releaseFailedBooking($booking, 'Midtrans Snap transaction could not be created.');
                $failedBooking->items()->delete();
                $failedBooking->delete();
            }

            throw $exception;
        }
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'upcoming' => $query
                ->where('status', '!=', Booking::STATUS_CANCELLED)
                ->whereHas('event', fn (Builder $event) => $event->where('starts_at', '>', now())),
            'past' => $query->where(function ($query): void {
                $query
                    ->where('status', Booking::STATUS_COMPLETED)
                    ->orWhereHas('event', fn (Builder $event) => $event->where('ends_at', '<', now()));
            }),
            'paid' => $query->where('payment_status', Booking::PAYMENT_PAID),
            'pending' => $query->where('payment_status', Booking::PAYMENT_PENDING),
            'cancelled' => $query->where('status', Booking::STATUS_CANCELLED),
            'refunded' => $query->where(function ($query): void {
                $query
                    ->where('status', Booking::STATUS_REFUNDED)
                    ->orWhere('payment_status', Booking::PAYMENT_REFUNDED);
            }),
            default => null,
        };
    }

    private function adminBookingRow(Booking $booking): array
    {
        $tickets = $booking->items->map(fn ($item): array => [
            'name' => $item->ticketType?->name ?? 'Ticket',
            'quantity' => (int) $item->quantity,
            'price' => (int) $item->unit_price,
        ])->values();

        return [
            'id' => (string) $booking->id,
            'reference' => $booking->reference,
            'user' => [
                'id' => (string) $booking->user?->id,
                'name' => $booking->user?->name ?? $booking->attendee_name ?? 'Guest',
                'email' => $booking->user?->email ?? $booking->attendee_email,
            ],
            'event' => [
                'title' => $booking->event?->title ?? 'Puncak Travellers event',
                'date' => $booking->event?->full_date_label ?? $booking->event?->starts_at?->timezone('Asia/Jakarta')->format('j M Y'),
                'location' => $booking->event?->location ?? 'Puncak region',
                'imageUrl' => $this->publicImageUrl($booking->event?->cover_image),
                'imageAlt' => $booking->event?->image_alt ?? $booking->event?->title ?? 'Puncak Travellers event',
            ],
            'tickets' => $tickets,
            'qty' => (int) $tickets->sum('quantity'),
            'total' => (int) $booking->total,
            'status' => $booking->status,
            'paymentStatus' => $booking->payment_status,
            'date' => $booking->created_at?->toIso8601String(),
        ];
    }

    private function adminBookingDetail(Booking $booking): array
    {
        return [
            ...$this->adminBookingRow($booking),
            'member' => [
                'name' => $booking->user?->name ?? $booking->attendee_name ?? 'Guest',
                'email' => $booking->user?->email ?? $booking->attendee_email,
                'phone' => '',
                'community' => $booking->user?->crew ?? 'Puncak Travellers',
            ],
            'subtotal' => (int) $booking->subtotal,
            'bookingFee' => (int) $booking->booking_fee,
            'currency' => $booking->currency ?? 'IDR',
            'timeline' => [
                [
                    'title' => 'Booking confirmed',
                    'time' => $booking->created_at?->timezone('Asia/Jakarta')->format('j M Y - H:i'),
                    'type' => 'confirmed',
                ],
            ],
        ];
    }

    private function bookingCard(Booking $booking): array
    {
        $event = $booking->event;
        $ticketCount = $booking->items->sum('quantity');
        $ticketNames = $booking->items
            ->map(fn ($item): string => $item->ticketType?->name ?? 'Ticket')
            ->map(fn (string $name): string => str($name)->before(' ')->toString())
            ->unique()
            ->join(' + ');

        $isCancelled = $booking->status === Booking::STATUS_CANCELLED;
        $isRefunded = $booking->status === Booking::STATUS_REFUNDED;

        $status = match (true) {
            $isCancelled || $isRefunded => 'past',
            $event?->status === 'completed' => 'past',
            default => 'upcoming',
        };

        $badge = match (true) {
            $isCancelled => 'Cancelled',
            $isRefunded => 'Refunded',
            $status === 'upcoming' => 'Upcoming in '.max(0, (int) now()->diffInDays($event?->starts_at, false)).' days',
            default => 'Completed',
        };

        $ticketAvailable = $booking->payment_status === Booking::PAYMENT_PAID
            && ! $isCancelled
            && ! $isRefunded;
        $primaryAction = match (true) {
            $ticketAvailable => 'View ticket',
            $isCancelled || $isRefunded => 'View details',
            default => 'Ticket unavailable',
        };

        return [
            'id' => $booking->reference,
            'status' => $status,
            'badge' => $badge,
            'title' => $event?->title ?? 'Puncak Travellers event',
            'date' => $event?->full_date_label ?? $event?->starts_at?->timezone('Asia/Jakarta')->format('D, j M Y - H:i'),
            'location' => $event?->location ?? $event?->place?->name ?? 'Puncak region',
            'reference' => $booking->reference,
            'paymentStatus' => $booking->payment_status,
            'ticketAvailable' => $ticketAvailable,
            'ticketLabel' => 'Tickets '.$ticketCount.($ticketNames ? ' - '.$ticketNames : ''),
            'primaryAction' => $primaryAction,
            'primaryHref' => $ticketAvailable ? "/api/puncak/bookings/{$booking->reference}/ticket" : '/account',
        ];
    }

    private function savedEventCard(Event $event): array
    {
        return [
            'id' => 'SAVED-'.str($event->slug)->upper()->replace('-', '_')->toString(),
            'status' => 'saved',
            'badge' => 'Saved',
            'title' => $event->title,
            'date' => $event->date_label ?? $event->starts_at?->timezone('Asia/Jakarta')->format('D, j M Y - H:i'),
            'location' => $event->location ?? $event->place?->name ?? 'Puncak region',
            'reference' => 'Saved event',
            'ticketLabel' => $event->spots_label ?? 'Book when ready',
            'primaryAction' => 'Book ticket',
            'primaryHref' => $event->booking_href ?? "/events/{$event->slug}/booking",
            'secondaryAction' => 'View details',
            'secondaryHref' => $event->detail_href ?? "/events/{$event->slug}",
        ];
    }

    private function publicImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
